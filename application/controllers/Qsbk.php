<?php
require('./vendor/autoload.php');
use QL\QueryList;
/**
 * @desc 糗百爬虫
 */
class Qsbk extends CI_Controller
{
	//翻页
	public function do_page()
	{
		$url = 'https://www.qiushibaike.com/text/page/';
		for ($i=0;$i<10;$i++){
			if ($i !==0 && $i !== 1){
				$url = $url.$i.'/';
				$this->q_text($url);
			}
		}
	}
	//文本爬取
	public function q_text($url)
	{
		$data = QueryList::get($url)
			// 设置采集规则
			->rules([
				'content'=>array('.contentHerf .content','text'),
			])
			->queryData();
		foreach ($data as $k=>$v){
			$map['content'] = $v['content'];
			$map['time'] = date('Y-m-d');
			$i_res = $this->db->insert('qiushibaike',$map);
		}
	}












}