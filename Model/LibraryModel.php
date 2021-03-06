<?php
/**
 * Created by PhpStorm.
 * User: SebastianN
 * Date: 08.02.17
 * Time: 09:35
 */

namespace RatePAY\Payment\Model;

use RatePAY\ModelBuilder;
class LibraryModel
{
    /**
     * LibraryModel constructor.
     * @param \RatePAY\Payment\Helper\Head\Head $rpHeadHelper
     * @param \RatePAY\Payment\Helper\Head\Additional $rpHeadAdditionalHelper
     * @param \RatePAY\Payment\Helper\Head\External $rpHeadExternalHelper
     * @param \RatePAY\Payment\Helper\Content\ContentBuilder $rpContentBuilder
     */
    public function __construct(
        \RatePAY\Payment\Helper\Head\Head $rpHeadHelper,
        \RatePAY\Payment\Helper\Head\Additional  $rpHeadAdditionalHelper,
        \RatePAY\Payment\Helper\Head\External $rpHeadExternalHelper,
        \RatePAY\Payment\Helper\Content\ContentBuilder $rpContentBuilder
    ) {
        $this->rpHeadHelper = $rpHeadHelper;
        $this->rpHeadAdditionalHelper = $rpHeadAdditionalHelper;
        $this->rpHeadExternalHelper = $rpHeadExternalHelper;
        $this->rpContentBuilder = $rpContentBuilder;
    }

    /**
     * Add adjustment items to the article list
     *
     * @param $creditmemo
     * @return array
     */
    public function addAdjustments($creditmemo)
    {
        $articles = [];

        if ($creditmemo->getAdjustmentPositive() > 0) {
            array_push($articles, ['Item' => $this->addAdjustment((float) $creditmemo->getAdjustmentPositive() * -1, 'Adjustment Refund', 'adj-ref')]);
        }

        if ($creditmemo->getAdjustmentNegative() > 0) {
            array_push($articles, ['Item' => $this->addAdjustment((float) $creditmemo->getAdjustmentNegative(), 'Adjustment Fee', 'adj-fee')]);
        }

        return $articles;
    }

    /**
     * Add merchant credit to artcile list
     *
     * @param $amount
     * @param $description
     * @param $articleNumber
     * @return array
     */
    public function addAdjustment($amount, $description, $articleNumber)
    {
        $tempVoucherItem = [];
        $tempVoucherItem['Description'] = $description;
        $tempVoucherItem['ArticleNumber'] = $articleNumber;
        $tempVoucherItem['Quantity'] = 1;
        $tempVoucherItem['UnitPriceGross'] = $amount;
        $tempVoucherItem['TaxRate'] = 0;

        return $tempVoucherItem;
    }

    /**
     * Build requests head section
     *
     * @param $quoteOrOrder
     * @param null $resultInit
     * @return /app/code/RatePAY/Payment/Model/Library/src/ModelBuilder mixed|ModelBuilder
     */
    public function getRequestHead($quoteOrOrder, $operation = null, $resultInit = null, $fixedPaymentMethod = null, $profileId = null, $securityCode = null)
    {
        $headModel = new ModelBuilder('Head');

        $headModel = $this->rpHeadHelper->setHead($quoteOrOrder, $headModel, $fixedPaymentMethod, $profileId, $securityCode);
        switch($operation){
            case 'CALCULATION_REQUEST' :
                break;

            case 'PAYMENT_REQUEST' :
                $this->rpHeadAdditionalHelper->setHeadAdditional($resultInit, $headModel);
                /*$headModel->setTransactionId($resultInit->getTransactionId());
                $headModel->setCustomerDevice(
                    $headModel->CustomerDevice()->setDeviceToken($this->customerSession->getRatepayDeviceIdentToken())
                );*/
                $headModel = $this->rpHeadExternalHelper->setHeadExternal($quoteOrOrder, $headModel);
                break;

            case "PAYMENT_CHANGE" :
                $headModel->setTransactionId($quoteOrOrder->getPayment()->getAdditionalInformation('transactionId'));
                break;

            case "CONFIRMATION_DELIVER" :
                $headModel->setTransactionId($quoteOrOrder->getPayment()->getAdditionalInformation('transactionId'));
                break;
        }

        return $headModel;
    }

    /**
     * Build requests content section
     *
     * @param $quoteOrOrder
     * @return ModelBuilder
     */
    public function getRequestContent($quoteOrOrder, $operation, $articleList = null, $amount = null, $fixedPaymentMethod = null)
    {
        $content = new ModelBuilder('Content');

        $contentArr = $this->rpContentBuilder->setContent($quoteOrOrder, $operation, $articleList, $amount, $fixedPaymentMethod);
        try{
            $content->setArray($contentArr);
        } catch (\Exception $e){
            echo $e->getMessage();
        }

        return $content ;
    }
}
