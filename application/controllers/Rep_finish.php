<?php
$env = 'local';
//$env = 'dev';
if ('local' == $env) {
	require('./vendor/autoload.php');
} else {
	require('/www/guzzle-master/vendor/autoload.php');
}
use QL\QueryList;
/**
 * @desc 银信爬虫
 */
class Rep_finish extends CI_Controller
{
	private $yx_baseurl = 'https://www.yinxinsirencaihang.com/';
	private $client = '';
	private $jar = '';
	private $finish_url = 'product/proudctFinished';
	//获取用户信息
	public function get_account()
	{
		$user = $this->db->select('account,pwd')
//			->limit(3)
			->get('fms_yx_account')->result_array();
//		print_r($user);die;
		foreach ($user as $k=>$v){
			$this->yx_login($v['account'],$v['pwd']);
		}
	}

	/**
	 * 模拟登陆
	 * @param $account
	 * @param $pwd
	 */
	public function yx_login($account, $pwd){//YX18001625170 yxt625170
		$this->client = new GuzzleHttp\Client();
		$this->jar = new \GuzzleHttp\Cookie\CookieJar();
		$url = $this->yx_baseurl."doLogin";
		$map['username'] = $account;
		$map['password'] = $pwd;
		$map['vcode'] = '';
		$map['hasToken'] = true;
		$retdata = $this->loginPage($url,$map);
		$login_info = json_decode($retdata,true);
		if ($login_info['msg'] == '成功' && $login_info['success']==1){
			$this->get_finish($map['username']);
		}else{
			$err_arr = ['err'=>$map];
			log_message('info',$err_arr);
			$this->get_user_account($account);
		}
	}
	/**
	 * 登陆
	 * @param $url
	 * @param $data
	 * @param string $method
	 * @return mixed
	 */
	function loginPage($url,$data,$method='post')
	{
		$response = $this->client->request($method,$url,['cookies' => $this->jar,'form_params' => $data])->getBody()->getContents();
		$_SESSION['jar_15618988191'] =  serialize($this->jar);
		return $response;
	}

	/**
	 * 爬取数据处理
	 * @param $username
	 */
	public function get_finish($username){
		$this->client = new GuzzleHttp\Client();
		$this->jar = unserialize($_SESSION['jar_15618988191']);
		$url = $this->yx_baseurl.$this->finish_url;
		$res =  $this->getPage($url,'post');
		$pos_start = strpos($res, 'id="beenFinishedForm"');
		$sub_str = substr($res,$pos_start);
		$pos_end = strpos($sub_str, 'id="applyBorrowForm"');
		$sub_str_end = substr($sub_str,0,$pos_end);
		$ql = QueryList::html($sub_str_end);
		$data1 = $ql->find('tr')->html();
//		print_r($data1);die;
		$trimtd = $this->trimtd($data1);
		$trimtd2 = $this->trimtd2($trimtd);
		$end_str_pos = strpos($trimtd2,'。');
		$sub_str_ends = substr($trimtd2,$end_str_pos);
		$exp_arr =  explode('。',$sub_str_ends);
		if (count($exp_arr)<8){
			$this->get_user_account($username);die;
		}
		foreach ($exp_arr as $k=>$v)
		{
			if (empty($v)){
				unset($exp_arr[$k]);
			}
		}
		$arr_chunk = array_chunk($exp_arr,9);
		foreach ($arr_chunk as $k=>$v)
		{
			$ql0 = QueryList::html($v[0]);
			$a0 = $ql0->find('a')->html();
			$ql8 = QueryList::html($v[8]);
			$a8 = $ql8->find('a')->html();
			$arr_chunk[$k][0] = $a0;
			$arr_chunk[$k][8] = $a8;
		}
		foreach ($arr_chunk as $k=>$v)
		{
			$arr_chunk[$k] = $this->trimall($v);
		}
		foreach ($arr_chunk as $k=>$v){
			$arr_chunks[$k]['pname'] = $v[0];
			$arr_chunks[$k]['lend_money'] = $v[1];
			$arr_chunks[$k]['lilv'] = $v[2];
			$arr_chunks[$k]['qishu'] = $v[3];
			$arr_chunks[$k]['zll'] = $v[4];
			$arr_chunks[$k]['f_date'] = $v[5];
			$arr_chunks[$k]['back_way'] = $v[6];
			$arr_chunks[$k]['f_status'] = $v[7];
			$arr_chunks[$k]['operate'] = $v[8];
			$arr_chunks[$k]['yx_account'] = $username;
			$arr_chunks[$k]['add_time'] = date('Y-m-d');
		}
		$this->db->insert_batch('yx_finish',$arr_chunks);//插入数据库
	}

	/**
	 * 去掉空格、回车、换行
	 * @param $str
	 * @return mixed
	 */
	function trimall($str){
		$qian=array(" ","　","\t","\n","\r");
		return str_replace($qian, '', $str);
	}

	/**
	 * 去掉td标签
	 * @param $str
	 * @return mixed
	 */
	function trimtd($str){
		$qian=array("<td>");
		return str_replace($qian, '。', $str);
	}

	/**
	 * 去掉td标签
	 * @param $str
	 * @return mixed
	 */
	function trimtd2($str){
		$qian=array("</td>");
		return str_replace($qian, '', $str);
	}
	/**
	 * @param $url
	 * @param string $method
	 * @return mixed
	 */
	function getPage($url,$method='get')
	{
		$response = $this->client->request($method,$url,['cookies' => $this->jar])->getBody()->getContents();
		return $response;
	}

	/**
	 * 获取用户账号
	 * @param $account
	 */
	public function get_user_account($account)
	{
		$ures = $this->db->select('id')->where('account',$account)->get('fms_yx_account')->row_array();
		$usres = $this->db->select('id,account,pwd')->where('id >',$ures['id'])->get('fms_yx_account')->result_array();
		if ($usres){
			foreach ($usres as $k=>$v){
				$this->yx_login($v['account'],$v['pwd']);
			}
		}else{
			print_r(['status'=>0,'数据爬取完成！']);
		}
	}








}