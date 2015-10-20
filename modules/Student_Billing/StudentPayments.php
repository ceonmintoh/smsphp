<?php

include_once('modules/Student_Billing/functions.inc.php');

if ( !$_REQUEST['print_statements'])
{
	DrawHeader(ProgramTitle());

	//Widgets('all');
	Search('student_id');
}

if ( $_REQUEST['values'] && $_POST['values'] && AllowEdit())
{
	foreach ( (array)$_REQUEST['values'] as $id=>$columns)
	{
		if ( $id!='new')
		{
			$sql = "UPDATE BILLING_PAYMENTS SET ";
							
			foreach ( (array)$columns as $column=>$value)
			{
				$sql .= $column."='".$value."',";
			}
			$sql = mb_substr($sql,0,-1) . " WHERE ID='".$id."'";
			DBQuery($sql);
		}
		else
		{
			$id = DBGet(DBQuery("SELECT ".db_seq_nextval('BILLING_PAYMENTS_SEQ').' AS ID'.FROM_DUAL));
			$id = $id[1]['ID'];

			$sql = "INSERT INTO BILLING_PAYMENTS ";

			$fields = 'ID,STUDENT_ID,SYEAR,SCHOOL_ID,PAYMENT_DATE,';
			$values = "'".$id."','".UserStudentID()."','".UserSyear()."','".UserSchool()."','".DBDate()."',";
			
			$go = 0;
			foreach ( (array)$columns as $column=>$value)
			{
				if ( !empty($value) || $value=='0')
				{
					if ( $column=='AMOUNT')
					{
						$value = preg_replace('/[^0-9.]/','',$value);
//FJ fix SQL bug invalid amount
						if ( !is_numeric($value))
							$value = 0;
					}
					$fields .= $column.',';
					$values .= "'".$value."',";
					$go = true;
				}
			}
			$sql .= '(' . mb_substr($fields,0,-1) . ') values(' . mb_substr($values,0,-1) . ')';
			
			if ( $go)
				DBQuery($sql);
		}
	}
	unset($_REQUEST['values']);
}

if ( $_REQUEST['modfunc']=='remove' && AllowEdit())
{
	if (DeletePrompt(_('Payment')))
	{
		DBQuery("DELETE FROM BILLING_PAYMENTS WHERE ID='".$_REQUEST['id']."' OR REFUNDED_PAYMENT_ID='".$_REQUEST['id']."'");
		unset($_REQUEST['modfunc']);
	}
}

if ( $_REQUEST['modfunc']=='refund' && AllowEdit())
{
	if (DeletePrompt(_('Payment'),_('Refund')))
	{
		$payment_RET = DBGet(DBQuery("SELECT COMMENTS,AMOUNT FROM BILLING_PAYMENTS WHERE ID='".$_REQUEST['id']."'"));
		DBQuery("INSERT INTO BILLING_PAYMENTS (ID,SYEAR,SCHOOL_ID,STUDENT_ID,AMOUNT,PAYMENT_DATE,COMMENTS,REFUNDED_PAYMENT_ID) values(".db_seq_nextval('BILLING_PAYMENTS_SEQ').",'".UserSyear()."','".UserSchool()."','".UserStudentID()."','".($payment_RET[1]['AMOUNT']*-1)."','".DBDate()."','".DBEscapeString($payment_RET[1]['COMMENTS'])." "._('Refund')."','".$_REQUEST['id']."')");
		unset($_REQUEST['modfunc']);
	}
}

if (UserStudentID() && !$_REQUEST['modfunc'])
{
	$payments_total = 0;
	$functions = array('REMOVE'=>'_makePaymentsRemove','AMOUNT'=>'_makePaymentsAmount','PAYMENT_DATE'=>'ProperDate','COMMENTS'=>'_makePaymentsTextInput','LUNCH_PAYMENT'=>'_lunchInput');
	
	$refunded_payments_RET = DBGet(DBQuery("SELECT '' AS REMOVE,ID,REFUNDED_PAYMENT_ID,AMOUNT,PAYMENT_DATE,COMMENTS FROM BILLING_PAYMENTS WHERE STUDENT_ID='".UserStudentID()."' AND SYEAR='".UserSyear()."' AND (REFUNDED_PAYMENT_ID IS NOT NULL)"),$functions,array('REFUNDED_PAYMENT_ID'));
	
	$payments_RET = DBGet(DBQuery("SELECT '' AS REMOVE,ID,REFUNDED_PAYMENT_ID,AMOUNT,PAYMENT_DATE,COMMENTS,LUNCH_PAYMENT FROM BILLING_PAYMENTS WHERE STUDENT_ID='".UserStudentID()."' AND SYEAR='".UserSyear()."' AND (REFUNDED_PAYMENT_ID IS NULL OR REFUNDED_PAYMENT_ID='') ORDER BY ID"),$functions);
	
	$i = 1;
	$RET = array();
	foreach ( (array)$payments_RET as $payment)
	{
		$RET[$i] = $payment;
		if ( $refunded_payments_RET[$payment['ID']])
		{
			$i++;
			$RET[$i] = ($refunded_payments_RET[$payment['ID']][1] + array('row_color'=>'FF0000'));
		}
		$i++;
	}

	if (count($RET) && !$_REQUEST['print_statements'] && AllowEdit())
		$columns = array('REMOVE'=>'');
	else
		$columns = array();
	
	$columns += array('AMOUNT'=>_('Amount'),'PAYMENT_DATE'=>_('Date'),'COMMENTS'=>_('Comment'),'LUNCH_PAYMENT'=>_('Lunch Payment'));
	if ( !$_REQUEST['print_statements'] && AllowEdit())
		$link['add']['html'] = array('REMOVE'=>button('add'),'AMOUNT'=>_makePaymentsTextInput('','AMOUNT'),'PAYMENT_DATE'=>ProperDate(DBDate()),'COMMENTS'=>_makePaymentsTextInput('','COMMENTS'),'LUNCH_PAYMENT'=>_lunchInput('','LUNCH_PAYMENT'));
	if ( !$_REQUEST['print_statements'])
	{
		echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'" method="POST">';
		//DrawStudentHeader();
		if (AllowEdit())
			DrawHeader('',SubmitButton(_('Save')));
		$options = array();
	}
	else
		$options = array('center'=>false,'add'=>false);

	ListOutput($RET,$columns,'Payment','Payments',$link,array(),$options);

	if ( !$_REQUEST['print_statements'] && AllowEdit())
		echo '<div class="center">' . SubmitButton( _( 'Save' ) ) . '</div>';

	echo '<BR />';

	$fees_total = DBGet(DBQuery("SELECT SUM(f.AMOUNT) AS TOTAL FROM BILLING_FEES f WHERE f.STUDENT_ID='".UserStudentID()."' AND f.SYEAR='".UserSyear()."'"));

	$table = '<TABLE class="align-right"><TR><TD>'._('Total from Fees').': '.'</TD><TD>'.Currency($fees_total[1]['TOTAL']).'</TD></TR>';

	$table .= '<TR><TD>'._('Less').': '._('Total from Payments').': '.'</TD><TD>'.Currency($payments_total).'</TD></TR>';

	$table .= '<TR><TD>'._('Balance').': <b>'.'</b></TD><TD><b>'.Currency(($fees_total[1]['TOTAL']-$payments_total),'CR').'</b></TD></TR></TABLE>';

	if ( !$_REQUEST['print_statements'])
		DrawHeader('','',$table);
	else
		DrawHeader($table,'','',null,null,true);
	
	if ( !$_REQUEST['print_statements'] && AllowEdit())
		echo '</FORM>';
}
