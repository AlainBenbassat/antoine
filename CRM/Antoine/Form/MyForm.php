<?php

use CRM_Antoine_ExtensionUtil as E;

class CRM_Antoine_Form_MyForm extends CRM_Core_Form {
  private $queue;
  private $queueName = 'demo_for_antoine';

  public function __construct() {
    // create the queue
    $this->queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $this->queueName,
      'reset' => FALSE, //do not flush queue upon creation
    ]);
    parent::__construct();
  }

  public function buildQuickForm() {

    $this->addRadio('action','What do you want to do?', $this->getActions(), [], '<br>', TRUE);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    // delete all items in the queue
    $this->queue->deleteQueue();

    // get the submitted values
    $values = $this->exportValues();
    if ($values['action'] == 'pers') {
      // select all individuals
      $sql = "select id from civicrm_contact where contact_type = 'Individual' limit 0,100";
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        // add the person id in the queue
        $task = new CRM_Queue_Task(['CRM_Antoine_Form_MyForm', 'doSomething'], [$dao->id]);
        $this->queue->createItem($task);
      }
    }
    else {
      CRM_Core_Session::setStatus('Not implemented yet', '', 'error');
    }

    // run the queue
    if ($this->queue->numberOfItems() > 0) {
      $runner = new CRM_Queue_Runner([
        'title' => 'Demo for Antoine',
        'queue' => $this->queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_CONTINUE,
        'onEnd' => ['CRM_Antoine_Form_MyForm', 'onEnd'],
        'onEndUrl' => CRM_Utils_System::url('civicrm/antoine', 'reset=1'),
      ]);
      $runner->runAllViaWeb();
    }

    parent::postProcess();
  }

  public function getActions() {
    $options = [
      'config' => 'Check configuration',
      'orgs' => 'Import organizations',
      'pers' => 'Import persons',
      'all' => 'Check configuration and import all'
    ];

    return $options;
  }

  public static function doSomething(CRM_Queue_TaskContext $ctx, $id) {
    return TRUE;
  }

  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    CRM_Core_Session::setStatus('Done with import', '', 'success');
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
