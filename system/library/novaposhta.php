<?php
/**
 * Minimal Nova Poshta API v2 client (cities / warehouses lookup only).
 * New file, no core files touched.
 */
class Novaposhta {
	private $api_key;
	private $log_file;

	const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

	public function __construct($api_key) {
		$this->api_key = (string)$api_key;
		$this->log_file = DIR_LOGS . 'novaposhta_api.log';
	}

	public function getCities($query, $limit = 20) {
		$result = $this->request('Address', 'getCities', array(
			'FindByString' => $query,
			'Limit'        => (int)$limit
		));

		$cities = array();

		foreach ($result as $row) {
			$cities[] = array(
				'ref'   => $row['Ref'],
				'title' => $row['Description']
			);
		}

		return $cities;
	}

	// $type: 'warehouse' (regular branches, default) or 'locker' (postomats only).
	public function getWarehouses($city_ref, $type = 'warehouse', $limit = 200) {
		$result = $this->request('Address', 'getWarehouses', array(
			'CityRef' => $city_ref,
			'Limit'   => (int)$limit
		));

		$warehouses = array();

		foreach ($result as $row) {
			$is_locker = mb_stripos($row['Description'], 'Поштомат') !== false;

			if (($type === 'locker') !== $is_locker) {
				continue;
			}

			$warehouses[] = array(
				'ref'         => $row['Ref'],
				'description' => $row['Description']
			);
		}

		return $warehouses;
	}

	private function request($model, $method, $properties) {
		if (!$this->api_key) {
			return array();
		}

		$payload = json_encode(array(
			'apiKey'           => $this->api_key,
			'modelName'        => $model,
			'calledMethod'     => $method,
			'methodProperties' => $properties
		));

		$curl = curl_init(self::API_URL);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($curl);
		$curl_error = curl_error($curl);
		curl_close($curl);

		if ($response === false) {
			$this->logError($model . '.' . $method . ' request failed: ' . $curl_error);
			return array();
		}

		$data = json_decode($response, true);

		if (!is_array($data) || empty($data['success'])) {
			$message = isset($data['errors']) ? implode('; ', (array)$data['errors']) : 'invalid response';
			$this->logError($model . '.' . $method . ' API error: ' . $message);
			return array();
		}

		return isset($data['data']) ? $data['data'] : array();
	}

	private function logError($message) {
		$line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
		file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
	}
}
