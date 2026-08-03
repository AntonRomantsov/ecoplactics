<?php
class ControllerCommonHome extends Controller {
	public function index() {
		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

		if (isset($this->request->get['route'])) {
			$canonical = $this->url->link('common/home');
			if ($this->config->get('config_seo_pro') && !$this->config->get('config_seopro_addslash')) {
				$canonical = rtrim($canonical, '/');
			}
			$this->document->addLink($canonical, 'canonical');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');


		// Reviewer-facing "how to check this" instructions, kept as plain
		// HTML in a separate file so the text can be edited without touching
		// any PHP/template code.
		$check_instructions_file = dirname(DIR_APPLICATION) . '/check-instructions.txt';
		$data['check_instructions'] = is_file($check_instructions_file) ? file_get_contents($check_instructions_file) : '';

		$check_readme4_file = dirname(DIR_APPLICATION) . '/check-instructions-level4-readme.txt';
		$data['check_readme_level4'] = is_file($check_readme4_file) ? file_get_contents($check_readme4_file) : '';

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}