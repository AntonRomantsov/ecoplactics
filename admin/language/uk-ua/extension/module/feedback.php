<?php
// Heading
$_['heading_title']    = 'Зворотній зв\'язок';

// Text
$_['text_extension']   = 'Розширення';
$_['text_home']        = 'Головна';
$_['text_edit']        = 'Редагування — Зворотній зв\'язок';
$_['text_success']     = 'Налаштування успішно збережено!';
$_['text_enabled']     = 'Увімкнено';
$_['text_disabled']    = 'Вимкнено';
$_['text_local_submissions'] = 'Заявки з сайту';
$_['text_local_pending']     = 'Ще не передано в SalesDrive: %d';
$_['text_salesdrive_sent']   = 'Передано заявок: %d';
$_['text_salesdrive_empty']  = 'Усі заявки вже передані в SalesDrive.';
$_['text_salesdrive_error']  = 'Помилка з\'єднання з SalesDrive';
$_['button_salesdrive']      = 'Передати в SalesDrive';
$_['text_contacts']    = 'Контакти (SalesDrive)';
$_['text_no_contacts'] = 'Контакти не знайдено.';
$_['text_dilovod_debug'] = 'Dilovod: відповідь API';
$_['text_dilovod_sent']  = 'Передано контактів: %d';
$_['text_dilovod_error'] = 'Помилка з\'єднання з Dilovod';
$_['text_group_salesdrive'] = 'SalesDrive';
$_['text_group_dilovod']    = 'Dilovod';
$_['text_group_telegram']   = 'Telegram-сповіщення';

// Entry
$_['entry_status']         = 'Статус';
$_['entry_api_key']        = 'API-ключ SalesDrive';
$_['entry_submit_url']     = 'URL відправки заявок (webhook)';
$_['entry_list_url']       = 'URL списку замовлень (для таблиці контактів)';
$_['entry_dilovod_api_url'] = 'URL API Dilovod';
$_['entry_dilovod_api_key'] = 'API-ключ Dilovod';
$_['entry_dilovod_firm_id'] = 'ID організації (firm) в Dilovod';
$_['entry_tg_token']        = 'Токен Telegram-бота';
$_['entry_tg_chat_id']      = 'Chat ID для сповіщень';

// Column
$_['column_id']        = '#';
$_['column_name']      = 'Ім\'я';
$_['column_phone']     = 'Телефон';

// Button
$_['button_save']      = 'Зберегти';
$_['button_cancel']    = 'Скасувати';
$_['button_dilovod']   = 'Передати в Dilovod';

// Help
$_['help_api_key']    = 'Необов\'язково. Без ключа заявки з форми "Зворотній зв\'язок" зберігаються тільки на сайті, без передачі в SalesDrive.';
$_['help_submit_url'] = 'Webhook-адреса SalesDrive для створення нової заявки з форми на сайті.';
$_['help_list_url']   = 'Адреса API SalesDrive для отримання списку замовлень/контактів (таблиця нижче).';
$_['help_tg_token']   = 'Отримати у @BotFather. Використовується для сповіщень про недоступність SalesDrive/Dilovod.';

// Error
$_['error_permission'] = 'Попередження: у вас немає прав для редагування цього модуля!';
$_['error_api']        = 'Попередження: не вдалося завантажити контакти з API SalesDrive.';
