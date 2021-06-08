<?php

namespace Drupal\lightning_charge_commerce\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\lightning_charge\Form\InvoiceForm;
use Drupal\lightning_charge\Invoice;
use Drupal\lightning_charge\InvoiceInterface;
use Drupal\lightning_charge\LightningChargeConstants;
use Drupal\lightning_charge\LightningChargeServiceInterface;
use Drupal\lightning_charge\MetaData;
use Drupal\lightning_charge\MetaDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the OrderInvoiceForm class.
 */
class OrderInvoiceForm extends InvoiceForm {

  /**
   * Provides the new mode.
   *
   * @var string
   */
  const MODE_NEW = 'new';

  /**
   * Provides the edit mode.
   *
   * @var string
   */
  const MODE_EDIT = 'edit';

  /**
   * Provides the new submit key.
   *
   * @var string
   */
  const KEY_NEW = 'submit_new';

  /**
   * Provides the delete key.
   *
   * @var string
   */
  const KEY_DELETE = 'delete';

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge\LightningChargeServiceInterface
   */
  protected $service;

  /**
   * Provides the current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    LightningChargeServiceInterface $service,
    AccountProxyInterface $current_user
  ) {
    $this->service = $service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lightning_charge'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lightning_charge_commerce_order_invoice_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL, $invoice = NULL, OrderInterface $commerce_order = NULL) {
    $form['#tree'] = TRUE;

    $mode = self::MODE_EDIT;

    if (is_null($invoice)) {
      $invoice = new Invoice();

      $mode = self::MODE_NEW;
    }

    $form['order'] = [
      '#type' => 'value',
      '#value' => $commerce_order,
    ];

    $form['item'] = [
      '#type' => 'value',
      '#value' => $invoice,
    ];

    $key = 'description';

    $default_value = $invoice->getDescription();

    $form[$key] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $default_value,
    ];

    $price = $commerce_order->getTotalPrice();

    // @todo Add crytocurrency form widget
    $key = 'amount';

    $default_value = $invoice->getAmount();

    if (!$default_value) {
      $default_value = [
        'number' => $price->getNumber(),
        'currency_code' => $price->getCurrencyCode(),
      ];
    }

    $form[$key] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Amount'),
      '#default_value' => $default_value,
    ];

    $key = 'currency';

    $options = $this->service->getCurrencies();

    $default_value = $invoice->getCurrency();

    if ($default_value) {
      $default_value = $price->getCurrencyCode();
    }

    $form[$key] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => $options,
      '#default_value' => $default_value,
    ];

    // @todo Change to duration widget
    $key = 'expiry';

    $default_value = $invoice->getExpirationDate();

    $form[$key] = [
      '#type' => 'number',
      '#title' => $this->t('Expiration'),
      '#default_value' => $default_value,
    ];

    if ($mode == self::MODE_EDIT) {
      // @todo Change to dropdown widget
      $key = 'status';

      $default_value = $invoice->getFormattedStatus();

      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => $default_value,
      ];
    }

    // @todo Refactor to form element plugin
    $key = 'metadata';

    $wrapper_id = 'wrapper-metadata';

    $prefix = '<div id="' . $wrapper_id . '">';
    $suffix = '</div>';

    $form[$key] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata'),
      '#open' => TRUE,
      '#prefix' => $prefix,
      '#suffix' => $suffix,
    ];

    $wrapper = &$form[$key];

    $key = 'items';

    $header = [];

    $header['delta'] = '';
    $header['key'] = $this->t('Key');
    $header['value'] = $this->t('Value');
    $header['weight'] = $this->t('Weight');
    $header['operations'] = $this->t('Operations');

    $group = 'weight';

    $wrapper[$key] = [
      '#type' => 'table',
      '#header' => $header,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group,
        ],
      ],
    ];

    $table = &$wrapper[$key];

    $parents = [
      'metadata',
      'items',
    ];

    $items = $form_state->getValue($parents, NULL);

    if (is_null($items)) {
      $items = $invoice->getMetadata();
    }

    $total = count($items);

    if (!$total) {
      $items[] = new MetaData('order_id', $commerce_order->id());
      $items[] = $this->newValue($items);
    }

    $delta = 0;

    foreach ($items as $item) {
      /** @var \Drupal\lightning_charge\MetaDataInterface $item */
      $args = [];

      $args['@delta'] = $delta + 1;

      $table[$delta] = [
        '#attributes' => [
          'class' => [
            'delta-' . $delta,
            'draggable',
          ],
        ],
        '#weight' => $delta,
      ];

      $row = &$table[$delta];

      $key = 'delta';

      $row[$key] = [
        '#type' => 'hidden',
        '#value' => $delta,
      ];

      $key = 'key';

      $default_value = $item->getKey();

      $row[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Key'),
        '#title_display' => 'hidden',
        '#default_value' => $default_value,
      ];

      $key = 'value';

      $default_value = $item->getValue();

      $row[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#title_display' => 'hidden',
        '#default_value' => $default_value,
      ];

      $key = 'weight';

      $default_value = $item->getWeight();

      $row[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Weight'),
        '#title_display' => 'hidden',
        '#default_value' => $default_value,
        '#attributes' => [
          'class' => [
            $group,
          ],
        ],
      ];

      $row['operations'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#op' => 'remove',
        '#name' => 'remove-' . $delta,
        '#delta' => $delta,
        '#ajax' => [
          'callback' => [
            $this,
            'ajaxCallback',
          ],
          'wrapper' => $wrapper_id,
        ],
        '#submit' => [
          [
            $this,
            'submitRemove',
          ],
        ],
        '#offset' => -3,
      ];

      $delta++;
    }

    $key = 'add';

    $wrapper[$key] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Another'),
      '#op' => $key,
      '#ajax' => [
        'callback' => [
          $this,
          'ajaxCallback',
        ],
        'wrapper' => $wrapper_id,
      ],
      '#submit' => [
        [
          $this,
          'submitAdd',
        ]
      ],
      '#offset' => -1,
    ];

    $key = 'actions';

    $form[$key] = [
      '#type' => 'actions',
    ];

    $actions = &$form[$key];

    $key = 'submit';

    $actions[$key] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $actions[self::KEY_NEW] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and Create Another'),
    ];

    if ($mode == self::MODE_EDIT && $this->currentUser->hasPermission(LightningChargeConstants::PERMISSION_DELETE)) {
      $actions[self::KEY_DELETE] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
      ];
    }

    return $form;
  }

  /**
   * Provides the add handler.
   */
  public function submitAdd($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    if ($triggering_element['#op'] != 'add') {
      return;
    }

    $parents = [
      'metadata',
      'items',
    ];

    $items = $form_state->getValue($parents, []);

    foreach ($items as &$item) {
      if (!$item instanceof MetaDataInterface) {
        $item = new MetaData($item['key'], $item['value'], $item['weight']);
      }
    }

    $items[] = $this->newValue($items);

    $form_state->setValue($parents, $items);

    $form_state->setRebuild();
  }

  /**
   * Returns a new value.
   *
   * @param \Drupal\lightning_charge\MetaDataInterface[] $existing
   *   An array of existing items.
   *
   * @return \Drupal\lightning_charge\MetaDataInterface
   *   A new metadata object.
   */
  public function newValue($existing) {
    return new MetaData();
  }

  /**
   * Provides the remove handler.
   */
  public function submitRemove($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    if ($triggering_element['#op'] != 'remove') {
      return;
    }

    $delta = $triggering_element['#delta'];

    $parents = [
      'metadata',
      'items',
    ];

    $values = $form_state->getValues();

    $items = NestedArray::getValue($values, $parents, $key_exists);

    if (!$key_exists) {
      $items = [];
    }

    array_splice($items, $delta, 1);

    foreach ($items as &$item) {
      if (!$item instanceof MetaDataInterface) {
        $item = new MetaData($item['key'], $item['value'], $item['weight']);
      }
    }

    $form_state->setValue($parents, $items);

    $input = &$form_state->getUserInput();

    NestedArray::setValue($input, $parents, $items);

    $form_state->setUserInput($input);

    $form_state->setRebuild();
  }

  /**
   * Provides the ajax callback.
   */
  public function ajaxCallback($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $key = '#offset';

    if (isset($triggering_element[$key])) {
      $offset = $triggering_element[$key];
    } else {
      $offset = -1;
    }

    $parents = $triggering_element['#parents'];
    array_splice($parents, $offset);

    $subform = NestedArray::getValue($form, $parents);

    return $subform;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $values = $form_state->cleanValues()->getValues();

    /** @var OrderInterface $order */
    $order = $values['order'];

    $route_name = 'lightning_charge_commerce.invoice.new';

    $route_parameters = [
      'commerce_order' => $order->id(),
    ];

    $options = [];

    $parents = [
      'actions',
      self::KEY_DELETE,
    ];

    $delete = NestedArray::getValue($form, $parents, $key_exists);

    if ($key_exists && $triggering_element['#value'] == $delete['#value']) {
      $destination = Url::fromRoute('lightning_charge_commerce.invoices', $route_parameters);
      $destination = $destination->toString();

      $options['query']['destination'] = $destination;

      /** @var InvoiceInterface $invoice */
      $invoice = $form_state->getValue('item');

      $id = $invoice->getId();

      $route_name = 'lightning_charge.invoice.delete';

      $route_parameters['invoice'] = $id;
    } else {
      $metadata = [];

      $parents = [
        'metadata',
        'items',
      ];

      $items = NestedArray::getValue($values, $parents);

      foreach ($items as $item) {
        $metadata[$item['key']] = $item['value'];
      }

      $metadata = array_filter($metadata);

      $values['metadata'] = $metadata;
      unset($values['actions']);
      unset($values['item']);
      unset($values['order']);

      $values['currency'] = $values['amount']['currency_code'];
      $values['amount'] = $values['amount']['number'];

      $result = $this->service->createInvoice($values);

      if (!$result instanceof InvoiceInterface) {
        $message = $this->t('An error occurred creating the invoice.');

        $this->messenger()->addError($message);
      }
      else {
        $args = [];

        $id = $result->getId();

        $args['@id'] = $id;

        $message = $this->t('Invoice @id has been created.', $args);

        $this->messenger()->addMessage($message);

        if ($triggering_element['#value'] == $form['actions'][self::KEY_NEW]['#value']) {
          $route_name = 'lightning_charge.invoice.new';
        }
        else {
          $route_name = 'lightning_charge.invoice.view';
          $route_parameters['invoice'] = $id;
        }
      }
    }

    $form_state->setRedirect($route_name, $route_parameters, $options);
  }

}
