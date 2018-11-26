<?php
$env = 'local';
//$env = 'dev';
if ('local' == $env) {
//	require('../shared/libraries/vendor/autoload.php');
	require('./vendor/autoload.php');
	require('./vendor/jaeger/querylist/src/QueryList.php');
} else {
	require('/www/guzzle-master/vendor/autoload.php');
}
use QL\QueryList;
/**
 * @desc 银信爬虫
 */
class Rep_backplan extends CI_Controller
{
	private $yx_baseurl = 'https://www.yinxinsirencaihang.com/';
	private $client = '';
	private $jar = '';
	private $finish_url = 'product/proudctFinished';
	//获取用户信息
	public function get_account()
	{
		$user = $this->db->select('account,pwd')
			->get('fms_yx_account')->result_array();
		foreach ($user as $k=>$v){
			$this->yx_login($v['account'],$v['pwd']);
		}
	}

	/**
	 * 模拟登陆
	 * @param $account
	 * @param $pwd
	 */
	public function yx_login($account='YX13901722417', $pwd = 'yxt722417'){//YX18001625170 yxt625170
		$this->client = new GuzzleHttp\Client();
		$this->jar = new \GuzzleHttp\Cookie\CookieJar();
		$url = $this->yx_baseurl."doLogin";
		$map['username'] = $account;
		$map['password'] = $pwd;
		$map['vcode'] = '';
		$map['hasToken'] = true;
		$retdata = $this->loginPage($url,$map);
		$login_info = json_decode($retdata,true);
//		print_r($login_info);die;
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
	public function get_finish($yx_account){
		$this->client = new GuzzleHttp\Client();
		$this->jar = unserialize($_SESSION['jar_15618988191']);
		$map['tabType'] = 'beenFinished';
		$map['currentPage'] = 1;
		$url = $this->yx_baseurl.$this->finish_url;
		$res =  $this->getPage($url,'post');
		$pos_start = strpos($res, 'id="beenFinishedForm"');
		$sub_str = substr($res,$pos_start);
		$pos_end = strpos($sub_str, 'id="applyBorrowForm"');
		$sub_str_end = substr($sub_str,0,$pos_end);
		$ql = QueryList::html($sub_str_end);
		$lend_title_url = $ql->find('.myInvestment_box_RepaymentIn_one_a1')->attrs('href')->all();//借款标题的连接
		print_r($lend_title_url);die;
		$title = $ql->find('.myInvestment_box_RepaymentIn_one_a1')->texts()->all();
		foreach ($lend_title_url as $k=>$v)
		{
			$res = $this->get_back_plan($v,$title[$k],$yx_account);
			if ($res){
				continue;
			}
		}

	}

	public function get_back_plan($url,$title,$yx_account)
	{
		$this->client = new GuzzleHttp\Client();
		$this->jar = unserialize($_SESSION['jar_15618988191']);
		$res =  $this->getPage($url,'get');
		$pos_start = strpos($res, 'reimbursementTabArea"');
		$sub_str = substr($res,$pos_start);
		$pos_end = strpos($sub_str, 'bidRecordTabArea');
		$sub_str_end = substr($sub_str,0,$pos_end);
		$ql = QueryList::html($sub_str_end);
		$data1 = $ql->find('td')->texts()->all();
		$arr_chunk = array_chunk($data1,7);
		if (empty($arr_chunk)){ //判断这个标题下是否有数据--如果有就继续执行，没有返回false跳出本次循环
			return false;
		}
		foreach ($arr_chunk as $k=>$v){
			$arr_chunks[$k]['qishu'] = $v[0];
			$arr_chunks[$k]['back_date'] = $v[1];
			$arr_chunks[$k]['b_interest'] = $v[2];
			$arr_chunks[$k]['principal'] = $v[3];
			$arr_chunks[$k]['l_interest'] = $v[4];
			$arr_chunks[$k]['f_interest'] = $v[5];
			$arr_chunks[$k]['status'] = $v[6];
			$arr_chunks[$k]['yx_account'] = $yx_account;
			$arr_chunks[$k]['title'] = $title;
			$arr_chunks[$k]['rep_time'] = date('Y-m-d');
		}
		$this->db->insert_batch('yx_back_plan',$arr_chunks);
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
			print_r(['status'=>1,'数据爬取完成！']);
		}
	}

	public function del()
	{
		$res = $this->db->where('id <','784')->delete('yx_back_plan');
		echo $this->db->last_query();
		print_r($res);die;
	}






}