<?php
/**
 * Created by PhpStorm.
 * User: SebastianN
 * Date: 20.02.17
 * Time: 14:59
 */

namespace RatePAY\Payment\Model\Method;

use Magento\Customer\Api\CustomerRepositoryInterface;
use RatePAY\Payment\Controller\LibraryController;
use RatePAY\Payment\Helper\Validator;
use Magento\Framework\Exception\PaymentException;

abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = false;

    /**
     * @var \RatePAY\Payment\Model\LibraryModel
     */
    protected $_rpLibraryModel;

    /**
     * @var \RatePAY\Payment\Model\Session
     */
    protected $rpSession;

    /**
     * @var \RatePAY\Payment\Helper\Data
     */
    protected $rpDataHelper;

    /**
     * @var \RatePAY\Payment\Helper\Validator
     */
    protected $rpValidator;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Can be used to install a different block for backend orders
     *
     * @var string
     */
    protected $_adminFormBlockType = null;

    /**
     * AbstractMethod constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \RatePAY\Payment\Model\LibraryModel $rpLibraryModel
     * @param \RatePAY\Payment\Model\Session $rpSession
     * @param \RatePAY\Payment\Helper\Data $rpDataHelper
     * @param Validator $rpValidator
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \RatePAY\Payment\Model\LibraryModel $rpLibraryModel,
        \RatePAY\Payment\Model\Session $rpSession,
        \RatePAY\Payment\Helper\Data $rpDataHelper,
        \RatePAY\Payment\Helper\Validator $rpValidator,
        \Magento\Checkout\Model\Session $checkoutSession,
        CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [])
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);

        $this->_rpLibraryModel = $rpLibraryModel;
        $this->rpSession = $rpSession;
        $this->rpDataHelper = $rpDataHelper;
        $this->rpValidator = $rpValidator;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
    }

    /**
     * call of ratepay requests moved to controller (Request.php)
     *
     * Authorize the transaction by calling PAYMENT_INIT, PAYMENT_REQUEST.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $this->getQuoteOrOrder();

        $head = $this->_rpLibraryModel->getRequestHead($order);
        $sandbox = (bool)$this->rpDataHelper->getRpConfigData($this->_code, 'sandbox');
        $company = $order->getBillingAddress()->getCompany();
        if (!$this->rpDataHelper->getRpConfigData($this->_code, 'b2b') && !empty($company)) {
            throw new PaymentException(__('b2b not allowed'));
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $diff = array_diff($this->rpDataHelper->getImportantAddressData($shippingAddress), $this->rpDataHelper->getImportantAddressData($billingAddress));

        if (!$this->rpDataHelper->getRpConfigData($this->_code, 'delivery_address') && count($diff)) {
            throw new PaymentException(__('ala not allowed'));
        }

        $resultInit = LibraryController::callPaymentInit($head, $sandbox);
        if ($resultInit->isSuccessful()) {
            $payment->setAdditionalInformation('transactionId', $resultInit->getTransactionId());
            $head = $this->_rpLibraryModel->getRequestHead($order, 'PAYMENT_REQUEST', $resultInit);
            $content = $this->_rpLibraryModel->getRequestContent($order, 'PAYMENT_REQUEST');
            $resultRequest = LibraryController::callPaymentRequest($head, $content, $sandbox);
            if (!$resultRequest->isSuccessful()) {
                if (!$resultRequest->isRetryAdmitted()){
                    $this->checkoutSession->setRatepayMethodHide(true);
                    $message = $this->formatMessage($resultRequest->getCustomerMessage());
                    $this->customerSession->setRatePayDeviceIdentToken(null);
                    throw new PaymentException(__($message)); // RatePAY Error Message
                } else {
                    $message = $this->formatMessage($resultRequest->getCustomerMessage());
                    throw new PaymentException(__($message)); // RatePAY Error Message
                }
            }
            $payment->setAdditionalInformation('descriptor', $resultRequest->getDescriptor());
            $this->checkoutSession->setRatepayMethodHide(false);
            $this->checkoutSession->unsRatepayIban();
            $this->customerSession->setRatePayDeviceIdentToken(null);
            return $this;
        } else {
            $message = $this->formatMessage($resultInit->getReasonMessage());
            $this->customerSession->setRatePayDeviceIdentToken(null);
            throw new PaymentException(__($message)); // RatePAY Error Message
        }
    }

    /**
     * Check if payment method is available
     *
     * 1) If quote is not null
     * 2) If a session variable is set, which indicates that the customer was declined by RatePAY within the PAYMENT_REQUEST
     * 3) If the basket amount is less then min order total amount or more than max order total amount
     * 4) If shipping address doesnt equals billing address
     * 5) If b2b is not allowed and billing address contains an company name
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if(is_null($quote)){
            return false;
        }

        if (parent::isAvailable($quote) == false) {
            return false;
        }

        $ratepayMethodHide = $this->checkoutSession->getRatepayMethodHide();
        if ($ratepayMethodHide == true) {
            return false;
        }

        if (!$this->rpDataHelper->getRpConfigData($this->_code, 'active')) {
            return false;
        }

        $totalAmount = $quote->getGrandTotal();
        $minAmount = $this->rpDataHelper->getRpConfigData($this->_code, 'min_order_total');
        $maxAmount = $this->rpDataHelper->getRpConfigData($this->_code, 'max_order_total');

        if ($totalAmount < $minAmount || $totalAmount > $maxAmount) {
            return false;
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$this->canUseForCountryDelivery($shippingAddress->getCountryId())) {
            return false;
        }

        return true;
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param $country
     * @return bool
     */
    public function canUseForCountryDelivery($country)
    {
        $availableCountries = explode(',', $this->rpDataHelper->getRpConfigData($this->_code, 'specificcountry_delivery'));
        if(!in_array($country, $availableCountries)){
            return false;
        }
        return true;
    }

    /**
     * @param object $additionalData
     */
    protected function handleInstallmentSessionParams($additionalData)
    {
        if ($additionalData->getRpTotalamount()) {
            $this->checkoutSession->setRatepayPaymentAmount($additionalData->getRpTotalamount());
            $this->checkoutSession->setRatepayInstallmentNumber($additionalData->getRpNumberofratesfull());
            $this->checkoutSession->setRatepayInstallmentAmount($additionalData->getRpRate());
            $this->checkoutSession->setRatepayLastInstallmentAmount($additionalData->getRpLastrate());
            $this->checkoutSession->setRatepayInterestRate($additionalData->getRpInterestrate());
        }
    }

    /**
     * @param \Magento\Framework\DataObject $data
     * @return $this
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $order = $this->getQuoteOrOrder();

        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = new \Magento\Framework\DataObject($data);
        }

        $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new \Magento\Framework\DataObject($additionalData ?: []);
        }
        if(!$this->customerSession->isLoggedIn()){
            $this->rpValidator->validateDob($additionalData);
        } else {
            if ($this->customerRepository->getById($this->customerSession->getCustomerId())->getDob() == null) {
                $this->rpValidator->validateDob($additionalData);
            }
        }

        if(!$order->getBillingAddress()->getTelephone()) {
            $this->rpValidator->validatePhone($additionalData);
        }

        $methodCode = $this->getQuoteOrOrder()->getPayment()->getMethod();

        $debitMethods = ['ratepay_de_directdebit', 'ratepay_at_directdebit', 'ratepay_nl_directdebit', 'ratepay_be_directdebit'];
        if (in_array($methodCode, $debitMethods) || !empty($additionalData->getRpIban()) // getRpIban used for installments
        ) {
            $this->rpValidator->validateIban($additionalData);
        }

        $installmentMethods = ['ratepay_de_installment', 'ratepay_at_installment', 'ratepay_de_installment0', 'ratepay_at_installment0'];
        if (in_array($methodCode, $installmentMethods)) {
            $this->handleInstallmentSessionParams($additionalData);
        }

        return $this;
    }

    /**
     * @param $message
     * @param $order
     * @return string
     */
    public function formatMessage($message)
    {
        if(empty($message)) {
            $message = __('Automated Data Procedure Error');
        }

        if(strpos($message, 'zusaetzliche-geschaeftsbedingungen-und-datenschutzhinweis') !== false){
            $message = $message . "\n\n" . $this->rpDataHelper->getRpConfigData($this->_code, 'privacy_policy');
        }

        return strip_tags($message);
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getQuoteOrOrder()
    {
        $paymentInfo = $this->getInfoInstance();

        if ($paymentInfo instanceof \Magento\Sales\Model\Order\Payment) {
            $quoteOrOrder = $paymentInfo->getOrder();
        } else {
            $quoteOrOrder = $paymentInfo->getQuote();
        }

        return $quoteOrOrder;
    }

    /**
     * Generates allowed months
     *
     * @param double $basketAmount
     * @return array
     */
    public function getAllowedMonths($basketAmount)
    {
        return [];
    }

    /**
     * Retrieve block type for method form generation
     *
     * @return string
     */
    public function getFormBlockType()
    {
        if ($this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML && $this->_adminFormBlockType !== null) {
            return $this->_adminFormBlockType;
        }
        return $this->_formBlockType;
    }
}
