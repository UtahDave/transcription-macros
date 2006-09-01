<?php

class Test extends Controller {

	function Test()
	{
		parent::Controller();
	}

	function index()
	{
		$this->load->view('welcome_message');
	}
}
?>