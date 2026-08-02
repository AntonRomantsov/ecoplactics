<?php
class ControllerExtensionShippingNovaposhta extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/shipping/novaposhta');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_novaposhta', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/novaposhta', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/shipping/novaposhta', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		$fields = array(
			'shipping_novaposhta_api_key'            => '',
			'shipping_novaposhta_delivery_warehouse' => 1,
			'shipping_novaposhta_delivery_doors'     => 1,
			'shipping_novaposhta_delivery_locker'    => 1,
			'shipping_novaposhta_cost_warehouse'     => '0.00',
			'shipping_novaposhta_cost_doors'         => '0.00',
			'shipping_novaposhta_cost_locker'        => '0.00',
			'shipping_novaposhta_sort_order'         => 5,
			'shipping_novaposhta_status'             => 0
		);

		foreach ($fields as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = ($this->config->get($key) !== null) ? $this->config->get($key) : $default;
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/novaposhta', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/novaposhta')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Nova Poshta API key is optional: without it the checkout falls back
		// to plain text city/warehouse fields instead of the live autocomplete.
		return !$this->error;
	}
}
