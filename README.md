# Magento 2 Forter Fraud Detection Module

Latest ver - 2.0.45 (November 2021)

---

## ✓ Install via composer (recommended)
Run the following command under your Magento 2 root dir:

```
composer require forter/magento2-module-forter
php bin/magento maintenance:enable
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento maintenance:disable
php bin/magento cache:flush
```

## Install manually under app/code
Download & place the contents of this repository under {YOUR-MAGENTO2-ROOT-DIR}/app/code/Forter/Forter  
Then, run the following commands under your Magento 2 root dir:
```
php bin/magento maintenance:enable
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento maintenance:disable
php bin/magento cache:flush
```

---

https://www.forter.com/

© 2020 Forter.
All rights reserved.

![Forter Logo](https://upload.wikimedia.org/wikipedia/commons/5/51/Forter_Logo_Blue_Web-3.png)
