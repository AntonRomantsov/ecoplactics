<?php
class ControllerExtensionModuleFeedback extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/feedback');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('module_feedback', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/feedback', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/feedback', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/module/feedback', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$fields = array(
			'module_feedback_status'          => 0,
			'module_feedback_api_key'         => '',
			'module_feedback_submit_url'      => '',
			'module_feedback_list_url'        => '',
			'module_feedback_dilovod_api_url' => '',
			'module_feedback_dilovod_api_key' => '',
			'module_feedback_dilovod_firm_id' => '',
			'module_feedback_tg_token'        => '',
			'module_feedback_tg_chat_id'      => ''
		);

		foreach ($fields as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = ($this->config->get($key) !== null) ? $this->config->get($key) : $default;
			}
		}

		$result = $this->fetchContacts();
		$data['contacts'] = $result['contacts'];
		$data['contacts_error'] = $result['contacts'] === null ? $this->language->get('error_api') : '';
		$data['ajax_dilovod'] = html_entity_decode($this->url->link('extension/module/feedback/sendToDilovod', 'user_token=' . $this->session->data['user_token'], true));
		$data['ajax_salesdrive'] = html_entity_decode($this->url->link('extension/module/feedback/syncToSalesDrive', 'user_token=' . $this->session->data['user_token'], true));

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "feedback_form WHERE status = 0");
		$data['local_pending'] = (int)$query->row['total'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/feedback', $data));
	}

	// Read-only "Contacts" list pulled live from SalesDrive - shows what's
	// currently sitting in SalesDrive, independent of our own local
	// oc_feedback_form copies. Returns null (not an empty array) on any
	// transport/parse failure so the template can tell "no contacts yet"
	// apart from "API unreachable".
	private function fetchContacts() {
		$api_key = $this->config->get('module_feedback_api_key');
		$list_url = $this->config->get('module_feedback_list_url');

		if (!$api_key || !$list_url) {
			return array('contacts' => null);
		}

		$curl = curl_init($list_url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'X-Api-Key: ' . $api_key),
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 15
		));
		$response = curl_exec($curl);
		curl_close($curl);

		if ($response === false) {
			return array('contacts' => null);
		}

		$json = json_decode($response, true);

		if ($json === null || !is_array($json)) {
			return array('contacts' => null);
		}

		if ((isset($json['status']) && $json['status'] === 'error') || isset($json['error'])) {
			return array('contacts' => null);
		}

		$rows = isset($json['data']) ? $json['data'] : $json;

		if (!is_array($rows) || empty($rows)) {
			return array('contacts' => null);
		}

		$contacts = array();

		foreach ($rows as $order) {
			if (!is_array($order)) {
				continue;
			}

			$contact = isset($order['primaryContact']) ? $order['primaryContact'] : array();
			$name = trim((isset($contact['fName']) ? $contact['fName'] : '') . ' ' . (isset($contact['lName']) ? $contact['lName'] : ''));
			$phones = isset($contact['phone']) ? $contact['phone'] : array();
			$phone = is_array($phones) ? (isset($phones[0]) ? $phones[0] : '—') : ($phones ?: '—');

			$contacts[] = array(
				'id'    => isset($contact['id']) ? $contact['id'] : null,
				'name'  => $name ?: '—',
				'phone' => $phone
			);
		}

		return array('contacts' => $contacts);
	}

	// AJAX: pushes every locally-collected feedback submission that hasn't
	// been forwarded yet (oc_feedback_form.status = 0) to SalesDrive as a
	// lead, marking it status = 1 once accepted. Safe to click repeatedly -
	// already-forwarded rows are simply skipped. Any SalesDrive failure
	// (including the whole API being unreachable) sends a Telegram alert.
	public function syncToSalesDrive() {
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', 'extension/module/feedback')) {
			$this->response->setOutput(json_encode(array('error' => 'No permission')));
			return;
		}

		$api_key = $this->config->get('module_feedback_api_key');
		$submit_url = $this->config->get('module_feedback_submit_url');

		if (!$api_key || !$submit_url) {
			$this->response->setOutput(json_encode(array('error' => 'SalesDrive API key/URL not configured')));
			return;
		}

		$query = $this->db->query("SELECT feedback_form_id, name, telephone FROM " . DB_PREFIX . "feedback_form WHERE status = 0");

		$sent = 0;
		$errors = array();

		foreach ($query->rows as $row) {
			$res = $this->pushLeadToSalesDrive($row['name'], $row['telephone']);

			if ($res['success']) {
				$this->db->query("UPDATE " . DB_PREFIX . "feedback_form SET status = 1 WHERE feedback_form_id = " . (int)$row['feedback_form_id']);
				$sent++;
			} else {
				$errors[] = $row['name'] . ': ' . $res['error'];
				$this->sendTelegramAlert('SalesDrive API: не вдалося передати заявку "' . $row['name'] . '". ' . $res['error']);
			}
		}

		if (empty($query->rows)) {
			$this->response->setOutput(json_encode(array('sent' => 0, 'errors' => array(), 'empty' => true)));
			return;
		}

		$this->response->setOutput(json_encode(array('sent' => $sent, 'errors' => $errors)));
	}

	private function pushLeadToSalesDrive($name, $telephone) {
		$api_key = $this->config->get('module_feedback_api_key');
		$submit_url = $this->config->get('module_feedback_submit_url');

		$payload = json_encode(array(
			'form'  => 'ecoplastics_feedback',
			'fName' => $name,
			'phone' => $telephone
		));

		$curl = curl_init($submit_url);
		curl_setopt_array($curl, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'X-Api-Key: ' . $api_key),
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 15
		));
		$response = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($curl);
		curl_close($curl);

		if ($response === false || $http_code >= 400) {
			return array('success' => false, 'error' => $curl_error ?: ('HTTP ' . $http_code));
		}

		return array('success' => true);
	}

	// AJAX: pulls the current SalesDrive contact list and pushes each one
	// into Dilovod as a lead (matched by presentation name, updated in
	// place if already present). Any failure (SalesDrive unreachable,
	// Dilovod unreachable, or an individual lead save failing) sends a
	// Telegram alert - this is the "notify if an API stops responding"
	// requirement for the customer-facing integration chain.
	public function sendToDilovod() {
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', 'extension/module/feedback')) {
			$this->response->setOutput(json_encode(array('error' => 'No permission')));
			return;
		}

		$result = $this->fetchContacts();

		if ($result['contacts'] === null) {
			$this->sendTelegramAlert('SalesDrive API недоступний: не вдалося завантажити контакти.');
			$this->response->setOutput(json_encode(array('error' => 'Failed to load contacts from SalesDrive')));
			return;
		}

		$existing_leads = $this->fetchDilovodLeads();

		if ($existing_leads === null) {
			$this->sendTelegramAlert('Dilovod API недоступний: не вдалося завантажити список лідів.');
			$this->response->setOutput(json_encode(array('error' => 'Failed to load leads from Dilovod')));
			return;
		}

		$sent = 0;
		$errors = array();
		$raw = array();

		foreach ($result['contacts'] as $contact) {
			$existing_id = isset($existing_leads[$contact['name']]) ? $existing_leads[$contact['name']] : null;
			$res = $this->dilovodSavePerson($contact['name'], $contact['phone'], $existing_id);
			$raw[] = array('name' => $contact['name'], 'response' => $res['raw']);

			if ($res['success']) {
				$sent++;
			} else {
				$errors[] = $contact['name'] . ': ' . $res['error'];
				$this->sendTelegramAlert('Dilovod API: не вдалося передати лід "' . $contact['name'] . '". ' . $res['error']);
			}
		}

		foreach ($raw as &$r) {
			$r['response'] = mb_convert_encoding(isset($r['response']) ? $r['response'] : '', 'UTF-8', 'auto');
		}

		$output = json_encode(
			array('sent' => $sent, 'errors' => $errors, 'raw' => $raw),
			JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);

		if ($output === false) {
			$output = json_encode(array('sent' => 0, 'errors' => array('json_encode error: ' . json_last_error_msg()), 'raw' => array()));
		}

		$this->response->setOutput($output);
	}

	private function fetchDilovodLeads() {
		$packet = json_encode(array(
			'version' => '0.25',
			'key'     => $this->config->get('module_feedback_dilovod_api_key'),
			'action'  => 'request',
			'params'  => array(
				'from'   => 'documents.lead',
				'fields' => array('id' => 'id', 'presentation' => 'presentation'),
				'limit'  => 1000
			)
		));

		$response = $this->dilovodRequest($packet);

		if (!$response) {
			return null;
		}

		$rows = json_decode($response, true);

		if (!is_array($rows)) {
			return null;
		}

		if (isset($rows['error']) || (isset($rows['status']) && $rows['status'] === 'error')) {
			return null;
		}

		$index = array();

		foreach ($rows as $lead) {
			$pres = isset($lead['presentation']) ? $lead['presentation'] : '';

			if ($pres && !isset($index[$pres])) {
				$index[$pres] = $lead['id'];
			}
		}

		return $index;
	}

	private function dilovodSavePerson($name, $phone, $existing_id = null) {
		// Dilovod stores the phone in two places at once (confirmed via the
		// "Developer" inspector on a lead saved through the Dilovod UI
		// itself): the plain top-level "phone" field, AND an entry inside
		// "details" - which is itself a JSON-encoded *string* (not a nested
		// object) holding a "phones" array of {pr, kind} pairs. Setting only
		// "phone" (the old approach) saves the lead without error but the
		// phone number never actually shows up anywhere.
		$digits = preg_replace('/\D+/', '', $phone);
		$formatted_phone = $digits ? '+' . $digits : '';

		$details = json_encode(array(
			'phones'     => $formatted_phone ? array(array('pr' => $formatted_phone, 'kind' => 'phone')) : array(),
			'emails'     => array(),
			'messengers' => array(),
			'urls'       => array(),
			'attributes' => array(),
			'notes'      => array()
		));

		$lead_packet = json_encode(array(
			'version' => '0.25',
			'key'     => $this->config->get('module_feedback_dilovod_api_key'),
			'action'  => 'saveObject',
			'params'  => array(
				'header' => array(
					'id'           => $existing_id ?: 'documents.lead',
					'firm'         => $this->config->get('module_feedback_dilovod_firm_id'),
					'date'         => date('Y-m-d H:i:s'),
					'phone'        => $formatted_phone,
					'details'      => $details,
					'presentation' => array('uk' => $name)
				)
			)
		));

		$lead_response = $this->dilovodRequest($lead_packet);
		$raw = $lead_response ?: 'cURL error';

		$lead_json = json_decode($lead_response, true);

		if (!$lead_json || isset($lead_json['error']) || !isset($lead_json['result']) || $lead_json['result'] !== 'ok') {
			return array('success' => false, 'error' => $lead_response, 'raw' => $raw);
		}

		return array('success' => true, 'raw' => $raw);
	}

	private function sendTelegramAlert($message) {
		$token = $this->config->get('module_feedback_tg_token');
		$chat_id = $this->config->get('module_feedback_tg_chat_id');

		if (!$token || !$chat_id) {
			return;
		}

		$url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
		$curl = curl_init($url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query(array('chat_id' => $chat_id, 'text' => $message)),
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT        => 8
		));
		curl_exec($curl);
		curl_close($curl);
	}

	private function dilovodRequest($packet) {
		$api_url = $this->config->get('module_feedback_dilovod_api_url');

		if (!$api_url) {
			return false;
		}

		$curl = curl_init($api_url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query(array('packet' => $packet)),
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 15
		));
		$response = curl_exec($curl);
		curl_close($curl);

		return $response;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/feedback')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
