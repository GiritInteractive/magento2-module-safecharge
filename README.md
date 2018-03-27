# Magento2-Safecharge_Safecharge
Safecharge_Safecharge - Magento 2 Payment Method Module
Installation Steps:
1. Place the contents of this repository under: {YOUR-MAGENTO2-ROOT-DIR}/app/code/Safecharge/Safecharge/
2. Open the terminal & run the following commands:
cd {YOUR-MAGENTO2-ROOT-DIR} && php bin/magento maintenance:enable && php bin/magento setup:upgrade && php bin/magento setup:di:compile && php bin/magento setup:static-content:deploy && php bin/magento maintenance:disable && php bin/magento cache:flush
3. Login to your admin panel & verify that a new payment method named 'Safecharge' appears under Stores > Configuration > Sales > Payment Methods.
4. Config your plugin & you're all set :)

*Replace {YOUR-MAGENTO2-ROOT-DIR} with your actual Magento2 root dir.
