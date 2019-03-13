<?php
/* Copyright (C) 2013 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/sellyoursaas/class/actions_sellyoursaas.class.php
 *	\ingroup    cabinetmed
 *	\brief      File to control actions
 */
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');


/**
 *	Class to manage hooks for module SellYourSaas
 */
class ActionsSellyoursaas
{
    var $db;
    var $error;
    var $errors=array();

    /**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
    }


    /**
     *    Return URL formated
     *
     *    @param	array			$parameters		Array of parameters
     *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *    @param    string			$action      	'add', 'update', 'view'
     *    @return   int         					<0 if KO,
     *                              				=0 if OK but we want to process standard actions too,
     *                              				>0 if OK and we want to replace standard actions.
     */
    function getNomUrl($parameters,&$object,&$action)
    {
    	global $db,$langs,$conf,$user;

    	if ($object->element == 'societe')
    	{
	    	// Dashboard
	    	if ($user->admin && ! empty($object->array_options['options_dolicloud']))
	    	{
	    		$url = '';
	    		if ($object->array_options['options_dolicloud'] == 'yesv1')
		    	{
		    		$url='https://www.on.dolicloud.com/signIn/index?email='.$object->email;	// Note that password may have change and not being the one of dolibarr admin user
		    	}
		    	if ($object->array_options['options_dolicloud'] == 'yesv2')
		    	{
		    		$dol_login_hash=dol_hash($conf->global->SELLYOURSAAS_KEYFORHASH.$object->email.dol_print_date(dol_now(),'dayrfc'), 5);	// hash is valid one hour
		    		$url=$conf->global->SELLYOURSAAS_ACCOUNT_URL.'?mode=logout_dashboard&username='.$object->email.'&password=&login_hash='.$dol_login_hash;
		    	}

		    	if ($url)
		    	{
			    	$this->resprints = ' - <!-- Added by getNomUrl hook of SellYourSaas -->';
			    	if ($object->array_options['options_dolicloud'] == 'yesv1') $this->resprints .= 'V1 - ';
			    	//if ($object->array_options['options_dolicloud'] == 'yesv2') $this->resprints .= 'V2 - ';
		    		//$this->resprints .= '<a href="'.$url.'" target="_myaccount" alt="'.$langs->trans("Dashboard").'"><span class="fa fa-desktop"></span> '.$conf->global->SELLYOURSAAS_NAME.' '.$langs->trans("Dashboard").'</a>';
			    	$this->resprints .= '<a href="'.$url.'" target="_myaccount" alt="'.$conf->global->SELLYOURSAAS_NAME.' '.$langs->trans("Dashboard").'"><span class="fa fa-desktop"></span></a>';
		    	}
	    	}
    	}

    	return 0;
    }

    /**
     *    Return ref customer formated
     *
     *    @param	array			$parameters		Array of parameters
     *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *    @param    string			$action      	'add', 'update', 'view'
     *    @return   int         					<0 if KO,
     *                              				=0 if OK but we want to process standard actions too,
     *                              				>0 if OK and we want to replace standard actions.
     */
    function getFormatedCustomerRef($parameters,&$object,&$action)
    {
        global $conf;

        if (! empty($parameters['objref']))
        {
            $isanurlofasellyoursaasinstance=false;
            $tmparray=explode(',',$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
            foreach($tmparray as $tmp)
            {
                if (preg_match('/'.preg_quote($tmp,'/').'$/', $parameters['objref'])) $isanurlofasellyoursaasinstance=true;
            }
            if ($isanurlofasellyoursaasinstance)
            {
                $this->results['objref'] = $parameters['objref'].' <a href="https://'.$parameters['objref'].'" target="_blank">'.img_picto('https://'.$parameters['objref'], 'object_globe').'</a>';
                return 1;
            }
        }

        return 0;
    }

    /**
     *    Execute action
     *
     *    @param	array	$parameters				Array of parameters
     *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *    @param    string	$action      			'add', 'update', 'view'
     *    @return   int         					<0 if KO,
     *                              				=0 if OK but we want to process standard actions too,
     *                              				>0 if OK and we want to replace standard actions.
     */
    function addMoreActionsButtons($parameters,&$object,&$action)
    {
    	global $db,$langs,$conf,$user;

    	dol_syslog(get_class($this).'::addMoreActionsButtons action='.$action);
    	$langs->load("sellyoursaas@sellyoursaas");

    	if (in_array($parameters['currentcontext'], array('contractcard'))
    		&& ! empty($object->array_options['options_deployment_status']))		// do something only for the context 'somecontext1' or 'somecontext2'
    	{
	    	if ($user->rights->sellyoursaas->write)
	    	{
	    		if (in_array($object->array_options['options_deployment_status'], array('processing', 'undeployed')))
	    		{
	    			$alt = $langs->trans("SellYourSaasSubDomains").' '.$conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES;
	    			$alt.= '<br>'.$langs->trans("SellYourSaasSubDomainsIP").' '.$conf->global->SELLYOURSAAS_SUB_DOMAIN_IP;

	    			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deploy" title="'.dol_escape_htmltag($alt).'">' . $langs->trans('Redeploy') . '</a>';
	    		}
	    		else
	    		{
	    			print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusProcessingOrUndeployed").'">' . $langs->trans('Redeploy') . '</a>';
	    		}

	    		if (in_array($object->array_options['options_deployment_status'], array('done')))
	    		{
	    			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refresh">' . $langs->trans('RefreshRemoteData') . '</a>';

	    			if (empty($object->array_options['options_fileauthorizekey']))
	    			{
	    				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreateauthorizedkeys">' . $langs->trans('RecreateAuthorizedKey') . '</a>';
	    			}

	    			if (empty($object->array_options['options_filelock']))
	    			{
		    			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreatelock">' . $langs->trans('RecreateLock') . '</a>';
	    			}
	    			else
	    			{
		    			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletelock">' . $langs->trans('SellYourSaasRemoveLock') . '</a>';
		    		}
	    		}
	    		else
	    		{
	    			print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('RefreshRemoteData') . '</a>';
	    		}

	    		if (in_array($object->array_options['options_deployment_status'], array('done')))
	    		{
	    			print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=undeploy">' . $langs->trans('Undeploy') . '</a>';
	    		}
	    		else
	    		{
	    			print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('Undeploy') . '</a>';
	    		}

	    		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=changecustomer" title="'.$langs->trans("ChangeCustomer").'">' . $langs->trans('ChangeCustomer') . '</a>';
	    	}
    	}

    	return 0;
    }



    /**
     *    Execute action
     *
     *    @param	array			$parameters		Array of parameters
     *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *    @param    string			$action      	'add', 'update', 'view'
     *    @return   int         					<0 if KO,
     *                              				=0 if OK but we want to process standard actions too,
     *                              				>0 if OK and we want to replace standard actions.
     */
    function doActions($parameters,&$object,&$action)
    {
        global $db,$langs,$conf,$user;

        $error = 0;

        dol_syslog(get_class($this).'::doActions action='.$action);
        $langs->load("sellyoursaas@sellyoursaas");

		/*
        if (is_object($object) && (get_class($object) == 'Contrat') && is_object($object->thirdparty))
        {
        	$object->email = $object->thirdparty->email;
        }*/


        if (in_array($parameters['currentcontext'], array('contractlist')))
        {
        	global $fieldstosearchall;

        	$fieldstosearchall['s.email']="ThirdPartyEmail";
        }

        if (in_array($parameters['currentcontext'], array('contractcard')))
        {
        	if ($action == 'deploy' || $action == 'deployall')
			{
				$db->begin();

				// SAME CODE THAN INTO MYACCOUNT INDEX.PHP

				// Disable template invoice
				$object->fetchObjectLinked();

				$foundtemplate=0;
				$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
				if (is_array($object->linkedObjects['facturerec']) && count($object->linkedObjects['facturerec']) > 0)
				{
					usort($object->linkedObjects['facturerec'], "cmp");

					//var_dump($object->linkedObjects['facture']);
					//dol_sort_array($object->linkedObjects['facture'], 'date');
					foreach($object->linkedObjects['facturerec'] as $idinvoice => $invoice)
					{
						if ($invoice->suspended == FactureRec::STATUS_SUSPENDED)
						{
							$result = $invoice->setStatut(FactureRec::STATUS_NOTSUSPENDED);
							if ($result <= 0)
							{
								$error++;
								$this->error=$invoice->error;
								$this->errors=$invoice->errors;
								setEventMessages($this->error, $this->errors, 'errors');
							}
						}
					}
				}

				if (! $error)
				{
					dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
					$sellyoursaasutils = new SellYourSaasUtils($db);
					$result = $sellyoursaasutils->sellyoursaasRemoteAction('deployall', $object, 'admin', $object->thirdparty->email, $object->array_options['options_deployment_init_adminpass']);
					if ($result <= 0)
					{
						$error++;
						$this->error=$sellyoursaasutils->error;
						$this->errors=$sellyoursaasutils->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// Finish deployall

				$comment = 'Activation after click on redeploy from contract card on '.dol_print_date(dol_now(), 'dayhourrfc');

				// Activate all lines
				if (! $error)
				{
					dol_syslog("Activate all lines - doActions deploy");

					$object->context['deployallwasjustdone']=1;		// Add a key so trigger into activateAll will know we have just made a "deployall"

					$result = $object->activateAll($user, dol_now(), 1, $comment);
					if ($result <= 0)
					{
						$error++;
						$this->error=$object->error;
						$this->errors=$object->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// End of deployment is now OK / Complete
				if (! $error)
				{
					$object->array_options['options_deployment_status'] = 'done';
					$object->array_options['options_deployment_date_end'] = dol_now();
					$object->array_options['options_undeployment_date'] = '';
					$object->array_options['options_undeployment_ip'] = '';

					$result = $object->update($user);
					if ($result < 0)
					{
						// We ignore errors. This should not happen in real life.
						//setEventMessages($contract->error, $contract->errors, 'errors');
					}
					else
					{
						setEventMessages($langs->trans("InstanceWasDeployed"), null, 'mesgs');
						setEventMessages($langs->trans("NoEmailSentToInformCustomer"), null, 'mesgs');
					}
				}

				if (! $error)
				{
					$db->commit();
				}
				else
				{
					$db->rollback();
				}


				$urlto=preg_replace('/action=[a-z]+/', '', $_SERVER['REQUEST_URI']);
				if ($urlto)
				{
					dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
					header("Location: ".$urlto);
					exit;
				}
			}

			if ($action == 'undeploy')
			{
				$db->begin();

				// SAME CODE THAN INTO MYACCOUNT INDEX.PHP

				// Disable template invoice
				$object->fetchObjectLinked();

				$foundtemplate=0;
				$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
				if (is_array($object->linkedObjects['facturerec']) && count($object->linkedObjects['facturerec']) > 0)
				{
					usort($object->linkedObjects['facturerec'], "cmp");

					//var_dump($object->linkedObjects['facture']);
					//dol_sort_array($object->linkedObjects['facture'], 'date');
					foreach($object->linkedObjects['facturerec'] as $idinvoice => $invoice)
					{
						if ($invoice->suspended == FactureRec::STATUS_NOTSUSPENDED)
						{
							$result = $invoice->setStatut(FactureRec::STATUS_SUSPENDED);
							if ($result <= 0)
							{
								$error++;
								$this->error=$invoice->error;
								$this->errors=$invoice->errors;
								setEventMessages($this->error, $this->errors, 'errors');
							}
						}
					}
				}

				if (! $error)
				{
					dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
					$sellyoursaasutils = new SellYourSaasUtils($db);
					$result = $sellyoursaasutils->sellyoursaasRemoteAction('undeploy', $object);
					if ($result <= 0)
					{
						$error++;
						$this->error=$sellyoursaasutils->error;
						$this->errors=$sellyoursaasutils->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// Finish deployall

				$comment = 'Close after click on undeploy from contract card';

				// Unactivate all lines
				if (! $error)
				{
					dol_syslog("Unactivate all lines - doActions undeploy");

					$result = $object->closeAll($user, 1, $comment);
					if ($result <= 0)
					{
						$error++;
						$this->error=$object->error;
						$this->errors=$object->errors;
						setEventMessages($this->error, $this->errors, 'errors');
					}
				}

				// End of undeployment is now OK / Complete
				if (! $error)
				{
					$object->array_options['options_deployment_status'] = 'undeployed';
					$object->array_options['options_undeployment_date'] = dol_now();
					$object->array_options['options_undeployment_ip'] = $_SERVER['REMOTE_ADDR'];

					$result = $object->update($user);
					if ($result < 0)
					{
						// We ignore errors. This should not happen in real life.
						//setEventMessages($contract->error, $contract->errors, 'errors');
					}
					else
					{
						setEventMessages($langs->trans("InstanceWasUndeployed"), null, 'mesgs');
						//setEventMessages($langs->trans("InstanceWasUndeployedToConfirm"), null, 'mesgs');
					}
				}

				if (! $error)
				{
					$db->commit();
				}
				else
				{
					$db->rollback();
				}

				$urlto=preg_replace('/action=[a-z]+/', '', $_SERVER['REQUEST_URI']);
				if ($urlto)
				{
					dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
					header("Location: ".$urlto);
					exit;
				}
			}

			if ($action == 'confirm_changecustomer')
			{
			    $db->begin();

			    $newid = GETPOST('socid', 'int');

			    if ($newid != $object->thirdparty->id)
			    {
			        $object->oldcopy = dol_clone($object);

			        $object->fk_soc = $newid;

			        if (! $error)
			        {
                        $result = $object->update($user, 1);
                        if ($result < 0)
                        {
                            $this->error = $object->error;
                            $this->errors = $object->errors;
                        }
			        }

                    if (! $error)
                    {
                        // TODO Update fk_soc of linked objects template invoice too
                        $object->fetchObjectLinked();

                        if (is_array($object->linkedObjectsIds['facturerec']))
                        {
                            foreach($object->linkedObjectsIds['facturerec'] as $key => $val)
                            {
                                $tmpfacturerec = new FactureRec($this->db);
                                $result = $tmpfacturerec->fetch($val);
                                if ($result > 0)
                                {
                                    $tmpfacturerec->oldcopy = dol_clone($tmpfacturerec);
                                    $tmpfacturerec->fk_soc = $newid;
                                    $result = $tmpfacturerec->update($user, 1);
                                    if ($result < 0)
                                    {
                                        $this->error = $tmpfacturerec->error;
                                        $this->errors = $tmpfacturerec->errors;
                                    }
                                }
                            }
                        }
                    }
			    }

			    if (! $error)
			    {
			        $db->commit();
			    }
			    else
			    {
			        $db->rollback();
			    }

			    $urlto=preg_replace('/action=[a-z]+/', '', $_SERVER['REQUEST_URI']);
			    if ($urlto)
			    {
			        dol_syslog("Redirect to page urlto=".$urlto." to avoid to do action twice if we do back");
			        header("Location: ".$urlto);
			        exit;
			    }
			}

			if (empty(GETPOST('instanceoldid','int')) && in_array($action, array('refresh','recreateauthorizedkeys','deletelock','recreatelock')))
			{
				dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
				$sellyoursaasutils = new SellYourSaasUtils($db);
				$result = $sellyoursaasutils->sellyoursaasRemoteAction($action, $object);
				if ($result <= 0)
				{
					$error++;
					$this->error=$sellyoursaasutils->error;
					$this->errors=$sellyoursaasutils->errors;
					setEventMessages($this->error, $this->errors, 'errors');
				}
				else
				{
					if ($action == 'refresh') setEventMessages($langs->trans("ResourceComputed"), null, 'mesgs');
					if ($action == 'recreateauthorizedkeys') setEventMessages($langs->trans("FileCreated"), null, 'mesgs');
					if ($action == 'recreatelock') setEventMessages($langs->trans("FileCreated"), null, 'mesgs');
					if ($action == 'deletelock') setEventMessages($langs->trans("FilesDeleted"), null, 'mesgs');
				}
			}
        }

        if (in_array($parameters['currentcontext'], array('thirdpartybancard')) && $action == 'sellyoursaastakepayment' && GETPOST('companymodeid','int') > 0)
        {
            // Define environment of payment modes
            $servicestatusstripe = 0;
            if (! empty($conf->stripe->enabled))
            {
                $service = 'StripeTest';
                $servicestatusstripe = 0;
                if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha') && empty($conf->global->SELLYOURSAAS_FORCE_STRIPE_TEST))
                {
                    $service = 'StripeLive';
                    $servicestatusstripe = 1;
                }
            }

            dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
            $sellyoursaasutils = new SellYourSaasUtils($db);
            //var_dump($service);var_dump($servicestatusstripe);

            include_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';
            $companypaymentmode = new CompanyPaymentMode($db);
            $companypaymentmode->fetch(GETPOST('companymodeid','int'));

            if ($companypaymentmode->id > 0)
            {
                $result = $sellyoursaasutils->doTakePaymentStripeForThirdparty($service, $servicestatusstripe, $object->id, $companypaymentmode, null, 0, 1, 1);
                if ($result > 0)
                {
                    $error++;
                    $this->error=$sellyoursaasutils->error;
                    $this->errors=$sellyoursaasutils->errors;
                    setEventMessages($sellyoursaasutils->description, null, 'errors');
                    setEventMessages($this->error, $this->errors, 'errors');
                }
                else
                {
                    setEventMessages($langs->trans("PaymentDoneOn".ucfirst($service)), null, 'mesgs');


                }
            }
            else
            {
                $error++;
                $this->error='Failed to fetch company payment mode for id '.GETPOST('companymodeid','int');
                $this->errors=null;
                setEventMessages($this->error, $this->errors, 'errors');
            }
        }

        dol_syslog(get_class($this).'::doActions end');
        return 0;
    }

    /**
     *    formConfirm
     *
     *    @param	array			$parameters		Array of parameters
     *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *    @param    string			$action      	'add', 'update', 'view'
     *    @return   int         					<0 if KO,
     *                              				=0 if OK but we want to process standard actions too,
     *                              				>0 if OK and we want to replace standard actions.
     */
    function formConfirm($parameters, &$object, &$action)
    {
        global $db, $langs, $conf, $user, $form;

        dol_syslog(get_class($this).'::doActions action='.$action);
        $langs->load("sellyoursaas@sellyoursaas");

        if ($action == 'changecustomer')
        {
            // Clone confirmation
            $formquestion = array(array('type' => 'other','name' => 'socid','label' => $langs->trans("SelectThirdParty"),'value' => $form->select_company($object->thirdparty->id, 'socid', '(s.client=1 OR s.client=2 OR s.client=3)')));
            $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ChangeCustomer'), '', 'confirm_changecustomer', $formquestion, 'yes', 1);
            $this->resprints = $formconfirm;
        }

        return 0;
    }


    /**
     * Complete search forms
     *
     * @param	array	$parameters		Array of parameters
     * @return	int						1=Replace standard code, 0=Continue standard code
     */
    function addSearchEntry($parameters)
    {
        global $conf, $langs, $user;

        if (! empty($user->rights->sellyoursaas->read) && ! empty($conf->global->SELLYOURSAAS_DOLICLOUD_ON))
        {
        	$langs->load("sellyoursaas@sellyoursaas");
	        $search_boxvalue = $parameters['search_boxvalue'];

	        $this->results['searchintocontract']=$parameters['arrayresult']['searchintocontract'];
	        $this->results['searchintocontract']['position']=22;

	        $this->results['searchintodolicloud']=array('position'=>23, 'img'=>'object_generic', 'label'=>$langs->trans("SearchIntoOldDoliCloudInstances", $search_boxvalue), 'text'=>img_picto('','object_generic').' '.$langs->trans("OldDoliCloudInstances", $search_boxvalue), 'url'=>dol_buildpath('/sellyoursaas/backoffice/dolicloud_list.php',1).'?search_multi='.urlencode($search_boxvalue));
        }

        return 0;
    }


    /**
     * Complete search forms
     *
     * @param	array			$parameters		Array of parameters
     * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @return	int								1=Replace standard code, 0=Continue standard code
     */
    function moreHtmlStatus($parameters, $object=null, $action='')
    {
    	global $conf, $langs, $user;
    	global $object;

    	if ($parameters['currentcontext'] == 'contractcard')
    	{
    		if (! empty($object->array_options['options_deployment_status']))
    		{
    			dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');
				$ret = '<br><br><div class="right bold">';
				$ispaid = sellyoursaasIsPaidInstance($object);
				if ($object->array_options['options_deployment_status'] == 'done')
				{
    				if ($ispaid)
    				{
    				    $ret .= '<span class="badge" style="font-size: 1em; background-color: green; color: #fff;">'.$langs->trans("PayedMode").'</span>';
    				    // nbofserviceswait, nbofservicesopened, nbofservicesexpired and nbofservicesclosed
    				    if (! $object->nbofservicesclosed)
    				    {
    				        $daysafterexpiration = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND;
    				        $ret.=' Service will be suspended<br>'.$daysafterexpiration.' days after expiration.';
    				    }
    				    if ($object->nbofservicesclosed)
    				    {
    				        $daysafterexpiration = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT;
    				        $ret.=' Service will be undeployed<br>'.$daysafterexpiration.' days after expiration.';
    				    }
    				}
    				else
    				{
    				    $ret .= '<span class="badge" style="font-size: 1em">'.$langs->trans("TrialMode").'</span>';
    				    // nbofserviceswait, nbofservicesopened, nbofservicesexpired and nbofservicesclosed
    				    if (! $object->nbofservicesclosed)
    				    {
    				        $daysafterexpiration = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND;
    				        $ret.=' Service will be suspended<br>'.$daysafterexpiration.' days after expiration.';
    				    }
    				    if ($object->nbofservicesclosed)
    				    {
    				        $daysafterexpiration = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT;
    				        $ret.=' Service will be undeployed<br>'.$daysafterexpiration.' days after expiration.';
    				    }
    				}
				}
				$ret .= '</div>';

				$this->resprints = $ret;
    		}
    	}

    	return 0;
    }


    /**
     * Complete search forms
     *
     * @param	array			$parameters		Array of parameters
     * @return	int								1=Replace standard code, 0=Continue standard code
     */
    function printEmail($parameters)
    {
    	global $conf, $langs, $user;
		global $object;
		//var_dump($parameters['currentcontext']);

		if (in_array($parameters['currentcontext'], array('thirdpartycard','thirdpartycontact','thirdpartycomm','thirdpartyticket','thirdpartynote','thirdpartydocument','contactthirdparty','projectthirdparty','consumptionthirdparty','thirdpartybancard','thirdpartymargins','ticketlist','thirdpartynotification','agendathirdparty')))
    	{
    		if ($object->element == 'societe')
    		{
    			// Dashboard
    			if ($user->admin && ! empty($object->array_options['options_dolicloud']))
    			{
    				$url='';
    				if ($object->array_options['options_dolicloud'] == 'yesv1')
    				{
    					$url='https://www.on.dolicloud.com/signIn/index?email='.$object->email;	// Note that password may have change and not being the one of dolibarr admin user
    				}
    				if ($object->array_options['options_dolicloud'] == 'yesv2')
    				{
    					$dol_login_hash=dol_hash($conf->global->SELLYOURSAAS_KEYFORHASH.$object->email.dol_print_date(dol_now(),'dayrfc'), 5);	// hash is valid one hour
    					$url=$conf->global->SELLYOURSAAS_ACCOUNT_URL.'?mode=logout_dashboard&username='.$object->email.'&password=&login_hash='.$dol_login_hash;
    				}

					if ($url)
					{
						$this->resprints = '<!-- Added by getNomUrl hook of SellYourSaas --><br><div class="clearboth">';
						if ($object->array_options['options_dolicloud'] == 'yesv1') $this->resprints .= 'V1 - ';
						if ($object->array_options['options_dolicloud'] == 'yesv2') $this->resprints .= 'V2 - ';
    					$this->resprints .= '<a href="'.$url.'" target="_myaccount" alt="'.$langs->trans("Dashboard").'"><span class="fa fa-desktop"></span> '.$conf->global->SELLYOURSAAS_NAME.' '.$langs->trans("Dashboard").'</a></div>';
					}
    			}
    		}
    	}

    	return 0;
    }



    /**
     * Complete search forms
     *
     * @param	array	$parameters		Array of parameters
     * @return	int						1=Replace standard code, 0=Continue standard code
     */
    function getDefaultFromEmail($parameters)
    {
    	global $conf, $langs, $user;
    	global $object;

    	$langs->load("sellyoursaas@sellyoursaas");

    	$result='';

    	if ($user->rights->sellyoursaas->read)
    	{
    		if (is_object($object))
	    	{
	    		$thirdparty = null;
	    		if (is_object($object->thirdparty)) $thirdparty = $object->thirdparty;
	    		elseif ($object->element == 'societe') $thirdparty = $object;

	    		if (is_object($thirdparty))
	    		{
		    		$categ_customer_sellyoursaas = $conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG;

		    		include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		    		$categobj = new Categorie($this->db);
		    		$categobj->fetch($categ_customer_sellyoursaas);

		    		// Search if customer is a dolicloud customer
		    		$hascateg = $categobj->containsObject('customer', $thirdparty->id);
					if ($hascateg) $result='senderprofile_1_1';
		    		//var_dump($hascateg);

		    		// Search if customer has a premium subscription
		    		//var_dump($object->thirdparty);

	    		}
	    	}
	    	$this->results['defaultfrom']=$result;
    	}

    	return 0;
    }


    /**
     * Run substitutions during ODT generation
     *
     * @param	array	$parameters		Array of parameters
     * @return	int						1=Replace standard code, 0=Continue standard code
     */
    function ODTSubstitution($parameters)
    {
    	global $conf, $langs;
    	global $object;

    	$langs->load("sellyoursaas@sellyoursaas");

    	$contract = $parameters['object'];

    	$parameters['substitutionarray']['sellyoursaas_version']=7;
    	$parameters['substitutionarray']['sellyoursaas_signature_logo']=DOL_DATA_ROOT.'/mycompany/notdownloadable/signature_owner.jpg';

    	return 0;
    }




    /**
     * Complete list
     *
     * @param	array	$parameters		Array of parameters
     * @param	object	$object			Object
     * @return	string					HTML content to add by hook
     */
    function printFieldListTitle($parameters,&$object)
    {
    	global $conf, $langs;
    	global $param, $sortfield, $sortorder;
		global $contextpage;

		if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo')))
    	{
    		$langs->load("sellyoursaas@sellyoursaas");
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID))
    			print_liste_field_titre("TrialOrPaid",$_SERVER["PHP_SELF"],'','',$param,' align="center"',$sortfield,$sortorder);
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED))
    			print_liste_field_titre("PaymentModeSaved",$_SERVER["PHP_SELF"],'','',$param,' align="center"',$sortfield,$sortorder);
    	}
    	if ($parameters['currentcontext'] == 'thirdpartybancard' && $parameters['linetype'] == 'stripetitle')
    	{
    	    $langs->load("sellyoursaas@sellyoursaas");
   	        print_liste_field_titre("",$_SERVER["PHP_SELF"],'','',$param,' align="center"',$sortfield,$sortorder);
    	}

    	return 0;
    }

    /**
     * Complete list
     *
     * @param	array	$parameters		Array of parameters
     * @param	object	$object			Object
     * @return	string					HTML content to add by hook
     */
    function printFieldListOption($parameters,&$object)
    {
    	global $conf, $langs;
    	global $contextpage;

    	if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo')))
    	{
    		//global $param, $sortfield, $sortorder;
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID))
    		{
    			print '<td class="liste_titre"></td>';
    		}
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED))
    		{
    			print '<td class="liste_titre"></td>';
    		}
    	}

    	return 0;
    }

    /**
     * Complete list
     *
     * @param	array	$parameters		Array of parameters
     * @param	object	$object			Object
     * @return	string					HTML content to add by hook
     */
    function printFieldListValue($parameters,&$object)
    {
    	global $conf, $langs;
    	global $db;
		global $contextpage;

		if ($parameters['currentcontext'] == 'contractlist' && in_array($contextpage, array('sellyoursaasinstances','sellyoursaasinstancesvtwo')))
    	{
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_TRIAL_OR_PAID)) // Field "Mode paid or free" not hidden
    		{
    			global $contractmpforloop;
	    		if (! is_object($contractmpforloop))
	    		{
	    			$contractmpforloop = new Contrat($db);
	    		}
	    		$contractmpforloop->id = $parameters['obj']->rowid ? $parameters['obj']->rowid : $parameters['obj']->id;
	    		$contractmpforloop->socid = $parameters['obj']->socid;
	    		print '<td align="center">';

	    		if (! preg_match('/\.on\./', $parameters['obj']->ref_customer))
	    		{
	    			if ($parameters['obj']->options_deployment_status != 'undeployed')
	    			{
		    			dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');
		    			$ret = '<div class="bold">';
		    			$ispaid = sellyoursaasIsPaidInstance($contractmpforloop);
		    			if ($ispaid) $ret .= '<span class="badge" style="font-size: 1em; background-color: green; color: #fff">'.$langs->trans("PayedMode").'</span>';
		    			else $ret .= '<span class="badge" style="font-size: 1em">'.$langs->trans("TrialMode").'</span>';
		    			$ret .= '</div>';

		    			print $ret;
	    			}
	    		}

	    		print '</td>';
    		}
    		if (empty($conf->global->SELLYOURSAAS_DISABLE_PAYMENT_MODE_SAVED))    // Field "Payment mode recorded" not hidden
    		{
    			global $companytmpforloop;
    			if (! is_object($companytmpforloop))
    			{
    				$companytmpforloop = new Societe($db);
    			}
    			$companytmpforloop->id = $parameters['obj']->socid;

    			$atleastonepaymentmode = sellyoursaasThirdpartyHasPaymentMode($companytmpforloop->id);

    			print '<td align="center">';
    			dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');
    			if ($atleastonepaymentmode) print $langs->trans("Yes");
    			print '</td>';
    		}
    	}

    	if ($parameters['currentcontext'] == 'thirdpartybancard')
    	{
    	    print '<td class="center">';
    	    if (! empty($parameters['obj']->rowid) && $parameters['linetype'] == 'stripecard')
    	    {
    	        $langs->load("sellyoursaas@sellyoursaas");
    	        print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&action=sellyoursaastakepayment&companymodeid='.$parameters['obj']->rowid.'">'.$langs->trans("PayBalance").'</a>';
    	    }
    	    print '</td>';
    	}

    	return 0;
    }


    /**
     * Execute action
     *
     * @param	array	$parameters		Array of parameters
     * @param   Object	$pdfhandler   	PDF builder handler
     * @param   string	$action     	'add', 'update', 'view'
     * @return  int 		        	<0 if KO,
     *                          		=0 if OK but we want to process standard actions too,
     *  	                            >0 if OK and we want to replace standard actions.
     */
    function afterPDFCreation($parameters,&$pdfhandler,&$action)
    {
    	global $conf,$langs;
    	global $hookmanager;

    	// If not a selyoursaas user, we leave
    	if (is_object($parameters['object']->thirdparty))
    	{
    		if (empty($parameters['object']->thirdparty->array_options['options_dolicloud']) || $parameters['object']->thirdparty->array_options['options_dolicloud'] == 'no')
    		{
				return 0;
    		}
    	}

    	// Same logo
    	if ($conf->global->SELLYOURSAAS_LOGO_SMALL == $conf->global->SOCIETE_LOGO_SMALL)
    	{
    		return 0;
    	}

    	// If this is a customer of SellYourSaas, we add logo of SellYourSaas
    	$outputlangs=$langs;

    	$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;

    	//var_dump($parameters['object']);

    	$ret=0;
    	dol_syslog(get_class($this).'::executeHooks action='.$action);

    	if (! is_object($parameters['object']))
    	{
    		dol_syslog("Trigger afterPDFCreation was called but parameter 'object' was not set by caller.", LOG_WARNING);
    		return 0;
    	}

    	$file = $parameters['file'];

    	// Create empty PDF
    	$pdf=pdf_getInstance();
    	if (class_exists('TCPDF'))
    	{
    		$pdf->setPrintHeader(false);
    		$pdf->setPrintFooter(false);
    	}
    	$pdf->SetFont(pdf_getPDFFont($outputlangs));

    	if ($conf->global->MAIN_DISABLE_PDF_COMPRESSION) $pdf->SetCompression(false);
    	//$pdf->SetCompression(false);

    	$pagecounttmp = $pdf->setSourceFile($file);
    	if ($pagecounttmp)
    	{
    		$tplidx = $pdf->ImportPage(1);
    		$s = $pdf->getTemplatesize($tplidx);
    		$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
    		$pdf->useTemplate($tplidx);

    		$logo = $conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->SELLYOURSAAS_LOGO_SMALL;

    		$height=pdf_getHeightForLogo($logo);
    		$pdf->Image($logo, 80, $this->marge_haute, 0, 10);	// width=0 (auto)
    	}
    	else
    	{
    		dol_syslog("Error: Can't read PDF content with setSourceFile, for file ".$file, LOG_ERR);
    	}

    	if ($pagecounttmp)
    	{
    		$pdf->Output($file,'F');
    		if (! empty($conf->global->MAIN_UMASK))
    		{
    			@chmod($file, octdec($conf->global->MAIN_UMASK));
    		}
    	}

    	return $ret;
    }
}


