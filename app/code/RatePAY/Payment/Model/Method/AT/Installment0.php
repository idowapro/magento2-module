<?php
/**
 * Created by PhpStorm.
 * User: SebastianN
 * Date: 27.02.17
 * Time: 16:05
 */

namespace RatePAY\Payment\Model\Method\AT;


class Installment0 extends \RatePAY\Payment\Model\Method\Installment0
{
    const METHOD_CODE = 'ratepay_at_installment0';

    protected $_code = self::METHOD_CODE;
}