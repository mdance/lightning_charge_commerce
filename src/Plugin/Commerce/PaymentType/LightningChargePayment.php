<?php

namespace Drupal\lightning_charge_commerce\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentDefault;

/**
 * Provides the lightning charge payment type.
 *
 * @CommercePaymentType(
 *   id = "lightning_charge_payment",
 *   label = @Translation("Lightning Charge Payment"),
 * )
 */
class LightningChargePayment extends PaymentDefault {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getLabel() {
    return parent::getLabel();
  }

  /**
   * {@inheritDoc}
   */
  public function getWorkflowId() {
    return parent::getWorkflowId();
  }

}
