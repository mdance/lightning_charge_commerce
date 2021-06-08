<?php

namespace Drupal\lightning_charge_commerce\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the lightning network payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "lightning_network",
 *   label = @Translation("Lightning Network"),
 * )
 */
class LightningNetwork extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [];

    $output = $this->t('Lightning Network');

    $title = $payment_method->title->value;

    if (!empty($title)) {
      $args['@title'] = $title;

      $output .= ' (@title)';
    }

    return $this->t($output, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['title'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Provides the title for this payment method.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
