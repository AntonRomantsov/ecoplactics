<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutPaymentAddress extends Controller {
	public function index() {
		$this->load->language('checkout/checkout');

		if (isset($this->session->data['payment_address']['address_id'])) {
			$data['address_id'] = $this->session->data['payment_address']['address_id'];
		} else {
			$data['address_id'] = $this->customer->getAddressId();
		}

		$this->load->model('account/address');

		$data['addresses'] = $this->model_account_address->getAddresses();

		if (isset($this->session->data['payment_address']['country_id'])) {
			$data['country_id'] = $this->session->data['payment_address']['country_id'];
		} else {
			$data['country_id'] = $this->config->get('config_country_id');
		}

		if (isset($this->session->data['payment_address']['zone_id'])) {
			$data['zone_id'] = $this->session->data['payment_address']['zone_id'];
		} else {
			$data['zone_id'] = '';
		}

		// Nova Poshta delivery no longer collects a full billing address here,
		// only the recipient's name/contact - default them from the account.
		$data['firstname'] = isset($this->session->data['payment_address']['firstname'])
			? $this->session->data['payment_address']['firstname']
			: $this->customer->getFirstname();
		$data['lastname'] = isset($this->session->data['payment_address']['lastname'])
			? $this->session->data['payment_address']['lastname']
			: $this->customer->getLastname();
		$data['email'] = $this->customer->getEmail();
		$data['telephone'] = $this->customer->getTelephone();

		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		// Custom Fields
		$data['custom_fields'] = array();

		$this->load->model('account/custom_field');

		$custom_fields = $this->model_account_custom_field->getCustomFields($this->config->get('config_customer_group_id'));

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'address') {
				$data['custom_fields'][] = $custom_field;
			}
		}

		if (isset($this->session->data['payment_address']['custom_field'])) {
			$data['payment_address_custom_field'] = $this->session->data['payment_address']['custom_field'];
		} else {
			$data['payment_address_custom_field'] = array();
		}

		$this->response->setOutput($this->load->view('checkout/payment_address', $data));
	}

	public function save() {
		$this->load->language('checkout/checkout');

		$json = array();

		// Validate if customer is logged in.
		if (!$this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!$json) {
			$this->load->model('account/address');

			// The simplified checkout form has no name field for logged-in
			// customers (already known) - default from the account so this
			// still validates even when firstname/lastname aren't posted.
			if (empty($this->request->post['firstname'])) {
				$this->request->post['firstname'] = $this->customer->getFirstname();
			}

			if (empty($this->request->post['lastname'])) {
				$this->request->post['lastname'] = $this->customer->getLastname();
			}

			if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}

			if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}

			// Nova Poshta delivery does not require a full billing address here -
			// the real delivery address (city + branch/locker) is collected in
			// the shipping address step. Fill placeholders instead of validating.
			if (empty($this->request->post['address_1'])) {
				$this->request->post['address_1'] = 'Нова Пошта';
			}

			if (empty($this->request->post['city'])) {
				$this->request->post['city'] = 'Україна';
			}

			if (empty($this->request->post['country_id'])) {
				$this->request->post['country_id'] = $this->config->get('config_country_id');
			}

			if (!isset($this->request->post['zone_id']) || $this->request->post['zone_id'] === '') {
				$this->request->post['zone_id'] = 0;
			}

			if (!$json) {
				$this->session->data['payment_address']['firstname'] = $this->request->post['firstname'];
				$this->session->data['payment_address']['lastname'] = $this->request->post['lastname'];
				$this->session->data['payment_address']['company'] = $this->request->post['company'];
				$this->session->data['payment_address']['address_1'] = $this->request->post['address_1'];
				$this->session->data['payment_address']['address_2'] = $this->request->post['address_2'];
				$this->session->data['payment_address']['postcode'] = $this->request->post['postcode'];
				$this->session->data['payment_address']['city'] = $this->request->post['city'];
				$this->session->data['payment_address']['country_id'] = $this->request->post['country_id'];
				$this->session->data['payment_address']['zone_id'] = $this->request->post['zone_id'];

				$this->load->model('localisation/country');

				$country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);

				if ($country_info) {
					$this->session->data['payment_address']['country'] = $country_info['name'];
					$this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
					$this->session->data['payment_address']['iso_code_3'] = $country_info['iso_code_3'];
					$this->session->data['payment_address']['address_format'] = $country_info['address_format'];
				} else {
					$this->session->data['payment_address']['country'] = '';
					$this->session->data['payment_address']['iso_code_2'] = '';
					$this->session->data['payment_address']['iso_code_3'] = '';
					$this->session->data['payment_address']['address_format'] = '';
				}

				$this->load->model('localisation/zone');

				$zone_info = $this->model_localisation_zone->getZone($this->request->post['zone_id']);

				if ($zone_info) {
					$this->session->data['payment_address']['zone'] = $zone_info['name'];
					$this->session->data['payment_address']['zone_code'] = $zone_info['code'];
				} else {
					$this->session->data['payment_address']['zone'] = '';
					$this->session->data['payment_address']['zone_code'] = '';
				}

				// Recipient telephone is not part of the standard payment_address
				// flow - persist it to the customer's own profile if changed.
				if (!empty($this->request->post['telephone']) && $this->request->post['telephone'] !== $this->customer->getTelephone()) {
					$this->load->model('account/customer');

					$this->model_account_customer->editCustomer($this->customer->getId(), array(
						'firstname'  => $this->customer->getFirstname(),
						'lastname'   => $this->customer->getLastname(),
						'email'      => $this->customer->getEmail(),
						'telephone'  => $this->request->post['telephone'],
						'newsletter' => $this->customer->getNewsletter()
					));
				}

				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}