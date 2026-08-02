<?php
class ModelExtensionShippingNovaposhta extends Model {
	public function getQuote($address) {
		$this->load->language('extension/shipping/novaposhta');

		$quote_data = array();

		if ($this->config->get('shipping_novaposhta_delivery_warehouse')) {
			$quote_data['warehouse'] = array(
				'code'         => 'novaposhta.warehouse',
				'title'        => $this->language->get('text_delivery_warehouse'),
				'cost'         => (float)$this->config->get('shipping_novaposhta_cost_warehouse'),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate((float)$this->config->get('shipping_novaposhta_cost_warehouse'), 0, $this->config->get('config_tax')), $this->session->data['currency'])
			);
		}

		if ($this->config->get('shipping_novaposhta_delivery_doors')) {
			$quote_data['doors'] = array(
				'code'         => 'novaposhta.doors',
				'title'        => $this->language->get('text_delivery_doors'),
				'cost'         => (float)$this->config->get('shipping_novaposhta_cost_doors'),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate((float)$this->config->get('shipping_novaposhta_cost_doors'), 0, $this->config->get('config_tax')), $this->session->data['currency'])
			);
		}

		if ($this->config->get('shipping_novaposhta_delivery_locker')) {
			$quote_data['locker'] = array(
				'code'         => 'novaposhta.locker',
				'title'        => $this->language->get('text_delivery_locker'),
				'cost'         => (float)$this->config->get('shipping_novaposhta_cost_locker'),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate((float)$this->config->get('shipping_novaposhta_cost_locker'), 0, $this->config->get('config_tax')), $this->session->data['currency'])
			);
		}

		if (!$quote_data) {
			return array();
		}

		return array(
			'code'       => 'novaposhta',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_novaposhta_sort_order'),
			'error'      => false
		);
	}
}
