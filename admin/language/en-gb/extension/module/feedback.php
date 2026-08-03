<?php
// Heading
$_['heading_title']    = 'Feedback';

// Text
$_['text_extension']   = 'Extensions';
$_['text_home']        = 'Home';
$_['text_edit']        = 'Edit Feedback';
$_['text_success']     = 'Settings saved successfully!';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_local_submissions'] = 'Submissions from the site';
$_['text_local_pending']     = 'Not yet forwarded to SalesDrive: %d';
$_['text_salesdrive_sent']   = 'Submissions transferred: %d';
$_['text_salesdrive_empty']  = 'Everything is already forwarded to SalesDrive.';
$_['text_salesdrive_error']  = 'Connection error with SalesDrive';
$_['button_salesdrive']      = 'Transfer to SalesDrive';
$_['text_contacts']    = 'Contacts (SalesDrive)';
$_['text_no_contacts'] = 'No contacts found.';
$_['text_dilovod_debug'] = 'Dilovod: API response';
$_['text_dilovod_sent']  = 'Contacts transferred: %d';
$_['text_dilovod_error'] = 'Connection error with Dilovod';
$_['text_group_salesdrive'] = 'SalesDrive';
$_['text_group_dilovod']    = 'Dilovod';
$_['text_group_telegram']   = 'Telegram alerts';

// Entry
$_['entry_status']         = 'Status';
$_['entry_api_key']        = 'SalesDrive API key';
$_['entry_submit_url']     = 'Submit URL (webhook)';
$_['entry_list_url']       = 'Order list URL (for the contacts table)';
$_['entry_dilovod_api_url'] = 'Dilovod API URL';
$_['entry_dilovod_api_key'] = 'Dilovod API key';
$_['entry_dilovod_firm_id'] = 'Dilovod firm ID';
$_['entry_tg_token']        = 'Telegram bot token';
$_['entry_tg_chat_id']      = 'Telegram chat ID';

// Column
$_['column_id']        = '#';
$_['column_name']      = 'Name';
$_['column_phone']     = 'Phone';

// Button
$_['button_save']      = 'Save';
$_['button_cancel']    = 'Cancel';
$_['button_dilovod']   = 'Transfer to Dilovod';

// Help
$_['help_api_key']    = 'Optional. Without a key, feedback submissions are stored on the site only, not forwarded to SalesDrive.';
$_['help_submit_url'] = 'SalesDrive webhook URL used to create a new lead from the site\'s feedback form.';
$_['help_list_url']   = 'SalesDrive API URL used to fetch the order/contact list shown below.';
$_['help_tg_token']   = 'Get this from @BotFather. Used for alerts when SalesDrive/Dilovod become unreachable.';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify this module!';
$_['error_api']        = 'Warning: Failed to load contacts from SalesDrive API.';
