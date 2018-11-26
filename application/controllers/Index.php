<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Index extends CI_Controller {

    public function sz3()
    {
		$this->load->view('lyh_bootstrap/sz3.html');
    }

	public function information()
	{
		$this->load->view('lyh_bootstrap/information.html');
	}
	public function about()
	{
		$this->load->view('lyh_bootstrap/about.html');
	}
	public function cass()
	{
		$this->load->view('lyh_bootstrap/cass.html');
	}

	public function tests_phpunit()
	{
	}


}

