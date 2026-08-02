<?php
// New file (no core files touched). OpenCart's Action class loads routed
// controllers with a plain include_once(), bypassing the OCMOD
// modification() override entirely - so checkout.php's step-number
// sprintf() calls cannot be patched via system/ecoplastics_checkout.ocmod.xml.
// The language array, however, IS event-aware (Loader::language() fires
// language/{route}/after with &$output), so the step numbers are corrected
// here instead: shipping method now appears before shipping address in the
// reordered checkout flow, so their step labels must swap too.
class ControllerEventCheckoutCustomization extends Controller {
	public function fixStepLabels(&$route, &$key, &$output) {
		if ($route !== 'checkout/checkout' || !is_array($output)) {
			return;
		}

		if (!isset($output['text_checkout_shipping_address']) || !isset($output['text_checkout_shipping_method'])) {
			return;
		}

		if (strpos($output['text_checkout_shipping_address'], 'Крок') !== false) {
			$output['text_checkout_shipping_address'] = 'Крок 4: Адреса доставки';
			$output['text_checkout_shipping_method']  = 'Крок 3: Спосіб доставки';
		} else {
			$output['text_checkout_shipping_address'] = 'Step 4: Delivery Details';
			$output['text_checkout_shipping_method']  = 'Step 3: Delivery Method';
		}
	}
}
