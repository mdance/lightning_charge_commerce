<?php

namespace Drupal\lightning_charge_commerce\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lightning_charge_commerce\LightningChargeCommerceConstants;

class AddPaymentMethodForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $entity = $this->entity;
    $bundle = $entity->bundle();

    $key = 'payment_details';

    $subform = &$form[$key];

    if ($bundle == LightningChargeCommerceConstants::BUNDLE) {
      $subform = $this->buildLightningNetworkForm($subform, $form_state);
    }

    return $form;
  }

  public function buildLightningNetworkForm(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $key = 'title';

    $default_value = $entity->$key->value ?? $this->t('Default');

    $description = $this->t('Please enter a title used to reference this payment method.');

    $form[$key] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#description' => $description,
      '#default_value' => $default_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $bundle = $entity->bundle();

    if ($bundle == LightningChargeCommerceConstants::BUNDLE) {
      $this->submitLightningNetworkForm($form['payment_details'], $form_state);
    }
  }

  /**
   * Provides the lightning network submit handler.
   *
   * @param array $element
   *   Provides the form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Provides the form state.
   */
  protected function submitLightningNetworkForm(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);

    $keys = [
      'title',
    ];

    foreach ($keys as $key) {
      if (isset($values[$key])) {
        $this->entity->$key = $values[$key];
      }
    }
  }

}
