<?php
/**
 * "Зворотній зв'язок" feedback modal - custom validation + spam
 * protection without a captcha. New file, no core files touched.
 *
 * Anti-spam (no captcha), matching the token/rate-limit/honeypot
 * approach used for this same modal in the candidate's earlier
 * submission of this assignment:
 * - Honeypot field ("website"): real visitors never see or fill it;
 *   any non-empty value means bot - pretend success, do nothing else.
 * - One-time session token: issued by token() when the modal opens,
 *   consumed by send(); must be at least MIN_TIME seconds old (proves
 *   a human took time to fill the form, not a scripted instant POST).
 * - Rate limit: max MAX_PER_HOUR submissions per session per rolling
 *   hour, independent of the token/honeypot checks.
 */
class ControllerCommonFeedback extends Controller {
	const MIN_TIME = 2;
	const TOKEN_TTL = 600;
	const MAX_PER_HOUR = 5;

	// Ukrainian mobile operator prefixes (2 digits after +38 0).
	private $operator_codes = array(
		'39', '50', '63', '66', '67', '68', '73',
		'91', '92', '93', '94', '95', '96', '97', '98', '99'
	);

	// AJAX: issue a one-time token when the modal opens.
	public function token() {
		$token = token(32);

		if (!isset($this->session->data['feedback_tokens'])) {
			$this->session->data['feedback_tokens'] = array();
		}

		$now = time();

		foreach ($this->session->data['feedback_tokens'] as $existing => $issued_at) {
			if ($now - $issued_at > self::TOKEN_TTL) {
				unset($this->session->data['feedback_tokens'][$existing]);
			}
		}

		$this->session->data['feedback_tokens'][$token] = $now;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('token' => $token)));
	}

	public function send() {
		$this->load->language('common/feedback');

		$json = array();

		if (!empty($this->request->post['website'])) {
			// Honeypot tripped - pretend success, do nothing else.
			$json['success'] = $this->language->get('text_success');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$token = isset($this->request->post['token']) ? $this->request->post['token'] : '';
		$tokens = isset($this->session->data['feedback_tokens']) ? $this->session->data['feedback_tokens'] : array();

		if (empty($token) || !isset($tokens[$token]) || (time() - $tokens[$token]) < self::MIN_TIME) {
			$json['error']['warning'] = $this->language->get('error_spam');
		} else {
			unset($this->session->data['feedback_tokens'][$token]);
		}

		if (!$json) {
			$now = time();

			if (!isset($this->session->data['feedback_log'])) {
				$this->session->data['feedback_log'] = array();
			}

			$this->session->data['feedback_log'] = array_filter($this->session->data['feedback_log'], function ($time) use ($now) {
				return ($now - $time) < 3600;
			});

			if (count($this->session->data['feedback_log']) >= self::MAX_PER_HOUR) {
				$json['error']['warning'] = $this->language->get('error_rate_limit');
			}
		}

		if (!$json) {
			$name = isset($this->request->post['name']) ? trim($this->request->post['name']) : '';

			if ((utf8_strlen($name) < 2) || (utf8_strlen($name) > 64) || !preg_match('/^[a-zA-Zа-яА-ЯіІїЇєЄёЁ\'\- ]+$/u', $name)) {
				$json['error']['name'] = $this->language->get('error_name');
			}

			$telephone_raw = isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '';
			$digits = preg_replace('/\D+/', '', $telephone_raw);

			// Accept with or without the leading country code.
			if (strlen($digits) === 12 && substr($digits, 0, 3) === '380') {
				$digits = substr($digits, 3);
			} elseif (strlen($digits) === 10 && $digits[0] === '0') {
				$digits = substr($digits, 1);
			}

			if (strlen($digits) !== 9 || !in_array(substr($digits, 0, 2), $this->operator_codes)) {
				$json['error']['telephone'] = $this->language->get('error_telephone');
			} else {
				$telephone = '+380' . $digits;
			}
		}

		if (!$json) {
			$this->session->data['feedback_log'][] = $now;

			$this->db->query("INSERT INTO " . DB_PREFIX . "feedback_form SET "
				. "name = '" . $this->db->escape($name) . "', "
				. "telephone = '" . $this->db->escape($telephone) . "', "
				. "message = '', "
				. "ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', "
				. "date_added = NOW()");

			// Push to SalesDrive if the admin has configured an API key
			// (Level 3, part 2) - silently skipped otherwise, the lead
			// stays in oc_feedback_form (status = 0) either way.
			if ($this->config->get('module_feedback_status') && $this->config->get('module_feedback_api_key')) {
				$this->sendToSalesDrive($name, $telephone);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function sendToSalesDrive($name, $telephone) {
		$api_url = $this->config->get('module_feedback_submit_url');
		$api_key = $this->config->get('module_feedback_api_key');

		if (!$api_url || !$api_key) {
			return;
		}

		$payload = json_encode(array(
			'form'  => 'ecoplastics_feedback',
			'fName' => $name,
			'phone' => $telephone
		));

		$curl = curl_init($api_url);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'X-Api-Key: ' . $api_key));
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);

		$response = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($curl);
		curl_close($curl);

		// A rejected request (e.g. an invalid API key) still gets a normal
		// HTTP response body from SalesDrive, not a cURL-level failure - so
		// $response === false alone would miss it. Check the status too.
		if ($response === false || $http_code >= 400) {
			$error = $curl_error ?: ('HTTP ' . $http_code . ': ' . $response);
			$this->logFeedbackError('SalesDrive request failed: ' . $error);
			$this->sendTelegramAlert('SalesDrive API: не вдалося передати заявку з форми "Зворотній зв\'язок" (' . $name . ', ' . $telephone . '). ' . $error);
		}
	}

	private function sendTelegramAlert($message) {
		$token = $this->config->get('module_feedback_tg_token');
		$chat_id = $this->config->get('module_feedback_tg_chat_id');

		if (!$token || !$chat_id) {
			return;
		}

		$curl = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
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

	private function logFeedbackError($message) {
		$line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
		file_put_contents(DIR_LOGS . 'feedback_api.log', $line, FILE_APPEND | LOCK_EX);
	}
}
