<?php
class ControllerExtensionShippingNovaposhta extends Controller {
	// AJAX: city autocomplete (only returns results when an API key is configured).
	public function cities() {
		$this->response->addHeader('Content-Type: application/json');

		$query = isset($this->request->get['q']) ? trim($this->request->get['q']) : '';
		$api_key = $this->config->get('shipping_novaposhta_api_key');

		if (!$api_key || $query === '') {
			$this->response->setOutput(json_encode(array()));
			return;
		}

		$cache_key = 'novaposhta.cities.' . md5(mb_strtolower($query));
		$cities = $this->cache->get($cache_key);

		if ($cities === false) {
			$np = new Novaposhta($api_key);
			$cities = $np->getCities($query);
			$this->cache->set($cache_key, $cities);
		}

		$this->response->setOutput(json_encode($cities));
	}

	// AJAX: warehouses/lockers for a chosen city (cached per city + type).
	public function warehouses() {
		$this->response->addHeader('Content-Type: application/json');

		$city_ref = isset($this->request->get['city_ref']) ? trim($this->request->get['city_ref']) : '';
		$type = (isset($this->request->get['type']) && $this->request->get['type'] === 'locker') ? 'locker' : 'warehouse';
		$api_key = $this->config->get('shipping_novaposhta_api_key');

		if (!$api_key || $city_ref === '') {
			$this->response->setOutput(json_encode(array()));
			return;
		}

		$cache_key = 'novaposhta.warehouses.' . $type . '.' . $city_ref;
		$warehouses = $this->cache->get($cache_key);

		if ($warehouses === false) {
			$np = new Novaposhta($api_key);
			$warehouses = $np->getWarehouses($city_ref, $type);
			$this->cache->set($cache_key, $warehouses);
		}

		$this->response->setOutput(json_encode($warehouses));
	}
}
