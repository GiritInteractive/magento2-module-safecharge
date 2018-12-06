<?php

namespace Safecharge\Safecharge\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Safecharge Safecharge config model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Config
{
    /**
     * Scope config object.
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Store manager object.
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Store id.
     *
     * @var int
     */
    private $storeId;

    /**
     * Already fetched config values.
     *
     * @var array
     */
    private $config = [];

    /**
     * Object initialization.
     *
     * @param ScopeConfigInterface  $scopeConfig Scope config object.
     * @param StoreManagerInterface $storeManager Store manager object.
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;

        $this->storeId = $this->getStoreId();
    }

    /**
     * Return config path.
     *
     * @return string
     */
    private function getConfigPath()
    {
        return sprintf('payment/%s/', Payment::METHOD_CODE);
    }

    /**
     * Return store manager.
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Return store id.
     *
     * @return int
     */
    private function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Return config field value.
     *
     * @param string $fieldKey Field key.
     *
     * @return mixed
     */
    private function getConfigValue($fieldKey)
    {
        if (isset($this->config[$fieldKey]) === false) {
            $this->config[$fieldKey] = $this->scopeConfig->getValue(
                $this->getConfigPath() . $fieldKey,
                ScopeInterface::SCOPE_STORE,
                $this->storeId
            );
        }

        return $this->config[$fieldKey];
    }

    /**
     * Return bool value depends of that if payment method is active or not.
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool)$this->getConfigValue('active');
    }

    /**
     * Return title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigValue('title');
    }

    /**
     * Return merchant id.
     *
     * @return string
     */
    public function getMerchantId()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_id');
        }

        return $this->getConfigValue('merchant_id');
    }

    /**
     * Return merchant site id.
     *
     * @return string
     */
    public function getMerchantSiteId()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_site_id');
        }

        return $this->getConfigValue('merchant_site_id');
    }

    /**
     * Return merchant secret key.
     *
     * @return string
     */
    public function getMerchantSecretKey()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_secret_key');
        }

        return $this->getConfigValue('merchant_secret_key');
    }

    /**
     * Return enable cc detection configuration value.
     *
     * @return bool
     */
    public function getUseCcDetection()
    {
        return (bool)$this->getConfigValue('enable_cc_detection');
    }

    /**
     * Return bool value depends of that if 3d secure is enabled or not.
     *
     * @return bool
     */
    public function is3dSecureEnabled()
    {
        return (bool)$this->getConfigValue('secure_3d');
    }

    /**
     * Return payment action configuration value.
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->getConfigValue('payment_action');
    }

    /**
     * Return payment solution configuration value.
     *
     * @return string
     */
    public function getPaymentSolution()
    {
        return $this->getConfigValue('payment_solution');
    }

    /**
     * Return currency configuration value.
     *
     * @return string
     */
    public function getCurrency()
    {
        $currency = $this->getConfigValue('currency');
        return (is_array($currency)) ? $currency : explode(',', (string) $this->getConfigValue('currency'));
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     */
    public function isTestModeEnabled()
    {
        if ($this->getConfigValue('mode') === Payment::MODE_LIVE) {
            return false;
        }

        return true;
    }

    /**
     * Return bool value depends of that if payment method debug mode
     * is enabled or not.
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        return (bool)$this->getConfigValue('debug');
    }

    /**
     * Return cc types.
     *
     * @return string
     */
    public function getCcTypes()
    {
        return $this->getConfigValue('cctypes');
    }

    /**
     * Return use vault configuration value.
     *
     * @return bool
     */
    public function getUseVault()
    {
        return (bool)$this->getConfigValue('use_vault');
    }

    /**
     * Return use ccv configuration value.
     *
     * @return bool
     */
    public function getUseCcv()
    {
        return (bool)$this->getConfigValue('useccv');
    }
}
