<?php
$env = 'local';
//$env = 'dev';
if ('local' == $env)
{
	require('./public/vendor/autoload.php');
} else
{
	require('/www/guzzle-master/vendor/autoload.php');
}

use QL\QueryList;

/**
 * @desc 银信爬虫
 */
class Rep_finish2 extends CI_Controller {
	private $yx_baseurl = 'https://www.yinxinsirencaihang.com/';
	private $client = '';
	private $jar = '';
	private $finish_url = 'product/proudctFinished';

	//获取用户信息
	public function get_account()
	{
		$user = $this->db->select('account,pwd')
			->get('fms_yx_account')->result_array();
		foreach ($user as $k => $v)
		{
			$this->yx_login($v['account'], $v['pwd']);
		}
	}

	/**
	 * 模拟登陆
	 * @param $account
	 * @param $pwd
	 */
	public function yx_login($account = 'YX15021757641', $pwd = 'yxt757641')
	{//YX18001625170 yxt625170
		$this->client = new GuzzleHttp\Client();
		$this->jar = new \GuzzleHttp\Cookie\CookieJar();
		$url = $this->yx_baseurl."doLogin";
		$map['username'] = $account;
		$map['password'] = $pwd;
		$map['vcode'] = '';
		$map['hasToken'] = TRUE;
		$retdata = $this->loginPage($url, $map);
		$login_info = json_decode($retdata, TRUE);
		if ($login_info['msg'] == '成功' && $login_info['success'] == 1)
		{
			$this->get_finish($map['username']);
		} else
		{
			$err_arr = ['err' => $map];
			log_message('info', $err_arr);
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
	function loginPage($url, $data, $method = 'post')
	{
		$response = $this->client->request($method, $url, ['cookies' => $this->jar, 'form_params' => $data])->getBody(
		)->getContents();
		$_SESSION['jar_15618988191'] = serialize($this->jar);

		return $response;
	}

	/**
	 * 爬取数据处理
	 * @param $username
	 */
	public function get_finish($username)
	{
		$this->client = new GuzzleHttp\Client();
		$this->jar = unserialize($_SESSION['jar_15618988191']);
		$url = $this->yx_baseurl.$this->finish_url;
		$res = $this->getPage($url, 'post');
		$pos_start = strpos($res, 'id="beenFinishedForm"');
		$sub_str = substr($res, $pos_start);
		$pos_end = strpos($sub_str, 'id="applyBorrowForm"');
		$sub_str_end = substr($sub_str, 0, $pos_end);
		$lend_title_url = QueryList::Query(
			$sub_str_end,
			array(
				'content' => array('td', 'text'),
			)
		)->data;
		if (empty($lend_title_url))
		{
			$this->get_user_account($username);
		}
//		print_r($lend_title_url);die;
		$arr_chunk = array_chunk($lend_title_url, 9);
		foreach ($arr_chunk as $k => $v)
		{
			$arr_chunks[$k]['pname'] = $v[0]['content'];
			$arr_chunks[$k]['lend_money'] = $v[1]['content'];
			$arr_chunks[$k]['lilv'] = $v[2]['content'];
			$arr_chunks[$k]['qishu'] = $v[3]['content'];
			$arr_chunks[$k]['zll'] = $v[4]['content'];
			$arr_chunks[$k]['f_date'] = $v[5]['content'];
			$arr_chunks[$k]['back_way'] = $v[6]['content'];
			$arr_chunks[$k]['f_status'] = $v[7]['content'];
			$arr_chunks[$k]['operate'] = $v[8]['content'];
			$arr_chunks[$k]['yx_account'] = $username;
			$arr_chunks[$k]['add_time'] = date('Y-m-d');
			$arr_chunks[$k]['order_date'] = date('Ymd');
			$arr_chunks[$k]['f_time_stamp'] = strtotime($v[5]['content']);
		}
		// print_r($arr_chunks);die;
		if (empty($arr_chunks[0]['pname']))
		{
			print_r($arr_chunks);
			echo $username;
			die;
		}
		$this->db->insert_batch('yx_finish', $arr_chunks);//插入数据库
	}

	/**
	 * 去掉空格、回车、换行
	 * @param $str
	 * @return mixed
	 */
	function trimall($str)
	{
		$qian = array(" ", "　", "\t", "\n", "\r");

		return str_replace($qian, '', $str);
	}

	/**
	 * 去掉td标签
	 * @param $str
	 * @return mixed
	 */
	function trimtd($str)
	{
		$qian = array("<td>");

		return str_replace($qian, '。', $str);
	}

	/**
	 * 去掉td标签
	 * @param $str
	 * @return mixed
	 */
	function trimtd2($str)
	{
		$qian = array("</td>");

		return str_replace($qian, '', $str);
	}

	/**
	 * @param $url
	 * @param string $method
	 * @return mixed
	 */
	function getPage($url, $method = 'get')
	{
		$response = $this->client->request($method, $url, ['cookies' => $this->jar])->getBody()->getContents();

		return $response;
	}

	/**
	 * 获取用户账号
	 * @param $account
	 */
	public function get_user_account($account)
	{
		$ures = $this->db->select('id')->where('account', $account)->get('fms_yx_account')->row_array();
		$usres = $this->db->select('id,account,pwd')->where('id >', $ures['id'])->get('fms_yx_account')->result_array();
		if ($usres)
		{
			foreach ($usres as $k => $v)
			{
				$this->yx_login($v['account'], $v['pwd']);
			}
		} else
		{
			print_r(['status' => 0, '数据爬取完成！']);
			die;
		}
	}


}