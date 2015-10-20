<?php
if (isset($_REQUEST['modfunc']) && $_REQUEST['modfunc']=='save')
{
	$date = $_REQUEST['day'].'-'.$_REQUEST['month'].'-'.$_REQUEST['year'];
	if (count($_REQUEST['month_values']))
	{
		foreach ( (array)$_REQUEST['month_values'] as $field_name=>$month)
		{
			$_REQUEST['values'][$field_name] = $_REQUEST['day_values'][$field_name].'-'.$month.'-'.$_REQUEST['year_values'][$field_name];
			if ( !VerifyDate($_REQUEST['values'][$field_name]))
			{
				if ( $_REQUEST['values'][$field_name]!='--')
					$warning[] = _('The date you specified is not valid, so was not used. The other data was saved.');

				unset($_REQUEST['values'][$field_name]);
			}
		}
	}

	if (count($_REQUEST['values']) && count($_REQUEST['student']))
	{
		if ( $_REQUEST['values']['GRADE_ID']!='')
		{
			$grade_id = $_REQUEST['values']['GRADE_ID'];
			unset($_REQUEST['values']['GRADE_ID']);
		}
		if ( $_REQUEST['values']['NEXT_SCHOOL']!='')
		{
			$next_school = $_REQUEST['values']['NEXT_SCHOOL'];
			unset($_REQUEST['values']['NEXT_SCHOOL']);
		}
		if ( $_REQUEST['values']['CALENDAR_ID'])
		{
			$calendar = $_REQUEST['values']['CALENDAR_ID'];
			unset($_REQUEST['values']['CALENDAR_ID']);
		}
		if ( $_REQUEST['values']['START_DATE']!='')
		{
			$start_date = $_REQUEST['values']['START_DATE'];
			unset($_REQUEST['values']['START_DATE']);
		}
		if ( $_REQUEST['values']['ENROLLMENT_CODE']!='')
		{
			$enrollment_code = $_REQUEST['values']['ENROLLMENT_CODE'];
			unset($_REQUEST['values']['ENROLLMENT_CODE']);
		}

		foreach ( (array)$_REQUEST['values'] as $field=>$value)
		{
			if (isset($value) && $value!='')
			{
				$update .= ','.$field."='".$value."'";
				$values_count++;
			}
		}

		foreach ( (array)$_REQUEST['student'] as $student_id=>$yes)
		{
			if ( $yes=='Y')
			{
				$students .= ",'".$student_id."'";
				$students_count++;

				//enrollment: update only the LAST enrollment record
				if ( $grade_id!='')
					DBQuery("UPDATE STUDENT_ENROLLMENT SET GRADE_ID='".$grade_id."' WHERE ID=(SELECT ID FROM STUDENT_ENROLLMENT WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND STUDENT_ID='".$student_id."' ORDER BY START_DATE DESC LIMIT 1)");

				if ( $next_school!='')
					DBQuery("UPDATE STUDENT_ENROLLMENT SET NEXT_SCHOOL='".$next_school."' WHERE ID=(SELECT ID FROM STUDENT_ENROLLMENT WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND STUDENT_ID='".$student_id."' ORDER BY START_DATE DESC LIMIT 1)");

				if ( $calendar)
					DBQuery("UPDATE STUDENT_ENROLLMENT SET CALENDAR_ID='".$calendar."' WHERE ID=(SELECT ID FROM STUDENT_ENROLLMENT WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND STUDENT_ID='".$student_id."' ORDER BY START_DATE DESC LIMIT 1)");

				if ( $start_date!='')
				{
					//FJ check if student already enrolled on that date when updating START_DATE
					$found_RET = DBGet(DBQuery("SELECT ID FROM STUDENT_ENROLLMENT WHERE STUDENT_ID='".$student_id."' AND SYEAR='".UserSyear()."' AND '".$start_date."' BETWEEN START_DATE AND END_DATE"));

					if (count($found_RET))
					{
						$error[] = _('The student is already enrolled on that date, and cannot be enrolled a second time on the date you specified. Please fix, and try enrolling the student again.');
					}
					else
					{
						DBQuery("UPDATE STUDENT_ENROLLMENT SET START_DATE='".$start_date."' WHERE ID=(SELECT ID FROM STUDENT_ENROLLMENT WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND STUDENT_ID='".$student_id."' ORDER BY START_DATE DESC LIMIT 1)");
					}
				}

				if ( $enrollment_code!='')
					DBQuery("UPDATE STUDENT_ENROLLMENT SET ENROLLMENT_CODE='".$enrollment_code."' WHERE ID=(SELECT ID FROM STUDENT_ENROLLMENT WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND STUDENT_ID='".$student_id."' ORDER BY START_DATE DESC LIMIT 1)");

			}
		}

		if ( $values_count && $students_count)
			DBQuery('UPDATE STUDENTS SET '.mb_substr($update,1).' WHERE STUDENT_ID IN ('.mb_substr($students,1).')');
		elseif (isset($warning))
			$warning[0] = mb_substr($warning,0,mb_strpos($warning,'. '));
		elseif ( $grade_id=='' && $next_school=='' && !$calendar && $start_date=='' && $enrollment_code=='')
			$warning[] = _('No data was entered.');

		if ( !isset($warning))
			$note[] = button('check') .'&nbsp;'._('The specified information was applied to the selected students.');
	}
	else
		$error[] = _('You must choose at least one field and one student');

	unset($_REQUEST['modfunc']);
	unset($_REQUEST['values']);
	unset($_SESSION['_REQUEST_vars']['modfunc']);
	unset($_SESSION['_REQUEST_vars']['values']);
}

DrawHeader(ProgramTitle());

if (isset($error))
	echo ErrorMessage($error);

if (isset($note))
	echo ErrorMessage($note, 'note');

if (isset($warning))
	echo ErrorMessage($warning, 'warning');


if (empty($_REQUEST['modfunc']))
{
	$extra['link'] = array('FULL_NAME'=>false);
	$extra['SELECT'] = ",CAST (NULL AS CHAR(1)) AS CHECKBOX";

	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=save" method="POST">';
		DrawHeader('',SubmitButton(_('Save')));
		echo '<BR />';

		if ( $_REQUEST['category_id'])
			$fields_RET = DBGet(DBQuery("SELECT ID,TITLE,TYPE,SELECT_OPTIONS FROM CUSTOM_FIELDS WHERE CATEGORY_ID='".$_REQUEST['category_id']."'"),array(),array('TYPE'));
		else
			$fields_RET = DBGet(DBQuery("SELECT ID,TITLE,TYPE,SELECT_OPTIONS FROM CUSTOM_FIELDS"),array(),array('TYPE'));

		$categories_RET = DBGet(DBQuery("SELECT ID,TITLE FROM STUDENT_FIELD_CATEGORIES"));

		//FJ css WPadmin
		echo '<div class="center">';

		$category_onchange_URL = "'" . PreparePHP_SELF( $_REQUEST, array( 'category_id' ) ) . "&category_id='";

		echo '<SELECT name="category_id" onchange="ajaxLink(' . $category_onchange_URL . ' + this.options[selectedIndex].value);">';

		echo '<OPTION value="">' . _( 'All Categories' ) . '</OPTION>';

		foreach ( (array)$categories_RET as $category)
			echo '<OPTION value="'.$category['ID'].'"'.($_REQUEST['category_id']==$category['ID']?' SELECTED':'').'>'.ParseMLField($category['TITLE']).'</OPTION>';
		echo '</SELECT>';

		echo '</div><TABLE class="widefat cellspacing-0 center col1-align-right">';

		if (count($fields_RET['text']))
		{
			foreach ( (array)$fields_RET['text'] as $field)
				echo '<TR><TD><b>'.ParseMLField($field['TITLE']).'</b></TD><TD>'._makeTextInput('CUSTOM_'.$field['ID']).'</TD></TR>';
		}

		if (count($fields_RET['numeric']))
		{
			foreach ( (array)$fields_RET['numeric'] as $field)
				echo '<TR><TD><b>'.ParseMLField($field['TITLE']).'</b></TD><TD>'._makeTextInput('CUSTOM_'.$field['ID'],true).'</TD></TR>';
		}

		if (count($fields_RET['date']))
		{
			foreach ( (array)$fields_RET['date'] as $field)
				echo '<TR><TD><b>'.ParseMLField($field['TITLE']).'</b></TD><TD>'._makeDateInput('CUSTOM_'.$field['ID']).'</TD></TR>';
		}

		if (count($fields_RET['select']))
		{
			foreach ( (array)$fields_RET['select'] as $field)
			{
				$select_options = array();
				$field['SELECT_OPTIONS'] = str_replace("\n","\r",str_replace("\r\n","\r",$field['SELECT_OPTIONS']));
				$options = explode("\r",$field['SELECT_OPTIONS']);
				if (count($options))
				{
					foreach ( (array)$options as $option)
						if ( $option!='')
							$select_options[$option] = $option;
				}

				echo '<TR><TD><b>'.ParseMLField($field[TITLE]).'</b></TD><TD>'._makeSelectInput('CUSTOM_'.$field['ID'],$select_options).'</TD></TR>';
			}
		}

		if (count($fields_RET['codeds']))
		{
			foreach ( (array)$fields_RET['codeds'] as $field)
			{
				$select_options = array();
				$field['SELECT_OPTIONS'] = str_replace("\n","\r",str_replace("\r\n","\r",$field['SELECT_OPTIONS']));
				$options = explode("\r",$field['SELECT_OPTIONS']);
				if (count($options))
				{
					foreach ( (array)$options as $option)
					{
						$option = explode('|',$option);
						if ( $option[0]!='' && $option[1]!='')
							$select_options[$option[0]] = $option[1];
					}
				}
				echo '<TR><TD><b>'.ParseMLField($field[TITLE]).'</b></TD><TD>'._makeSelectInput('CUSTOM_'.$field['ID'],$select_options).'</TD></TR>';
			}
		}

		// TODO: (see Search.fnc.php)
		// merge select, autos, edits, exports & codeds
		// (same or similar SELECT output)
		foreach ( (array)$fields_RET['autos'] as $field )	
		{
			$select_options = array();

			$options = explode(
				'<br />',
				nl2br( $field['SELECT_OPTIONS'] )
			);

			foreach ( (array)$options as $option )
				if ( $option != '' )
					$select_options[$option] = $option;

			// add the 'new' option, is also the separator
			$select_options['---'] = '-' . _( 'Edit' ) . '-';

			$field_name = 'CUSTOM_' . $field['ID'];

			// add values found in current and previous year
			$options_RET = DBGet( DBQuery( "SELECT DISTINCT s." . $field_name . ",upper(s." . $field_name . ") AS KEY
				FROM STUDENTS s,STUDENT_ENROLLMENT sse
				WHERE sse.STUDENT_ID=s.STUDENT_ID
				AND (sse.SYEAR='" . UserSyear() . "' OR sse.SYEAR='" . ( UserSyear() - 1 ) . "')
				AND s." . $field_name . " IS NOT NULL
				AND s." . $field_name . " != ''
				ORDER BY KEY" ) );
			
			foreach ( (array)$options_RET as $option )
				if ( !in_array( $option[$field_name], $options ) )
					$select_options[$option[$field_name]] = array(
						$option[$field_name],
						'<span style="color:blue">' . $option[$field_name] . '</span>'
					);

			echo '<TR><TD><b>'.ParseMLField($field['TITLE']).'</b></TD><TD>'._makeSelectInput($field_name,$select_options).'</TD></TR>';
		}

		if (count($fields_RET['edits']))
			{
			foreach ( (array)$fields_RET['edits'] as $field)
			{
				$select_options = array();
				$field['SELECT_OPTIONS'] = str_replace("\n","\r",str_replace("\r\n","\r",$field['SELECT_OPTIONS']));
				$options = explode("\r",$field['SELECT_OPTIONS']);
				if (count($options))
				{
					foreach ( (array)$options as $option)
						if ( $option!='')
							$select_options[$option] = $option;
				}
				// add the 'new' option
//FJ new option
//				$select_options['---'] = '---';
				$select_options['---'] = '-'. _('Edit') .'-';

				echo '<TR><TD><b>'.ParseMLField($field[TITLE]).'</b></TD><TD>'._makeSelectInput('CUSTOM_'.$field['ID'],$select_options).'</TD></TR>';
			}
		}

		if (count($fields_RET['exports']))
		{
			foreach ( (array)$fields_RET['exports'] as $field)
			{
				$select_options = array();
				$field['SELECT_OPTIONS'] = str_replace("\n","\r",str_replace("\r\n","\r",$field['SELECT_OPTIONS']));
				$options = explode("\r",$field['SELECT_OPTIONS']);
				if (count($options))
				{
					foreach ( (array)$options as $option)
					{
						$option = explode('|',$option);
						if ( $option[0]!='')
							$select_options[$option[0]] = $option[0];
					}
				}
				echo '<TR><TD><b>'.ParseMLField($field[TITLE]).'</b></TD><TD>'._makeSelectInput('CUSTOM_'.$field['ID'],$select_options).'</TD></TR>';
			}
		}

		if (count($fields_RET['textarea']))
		{
			foreach ( (array)$fields_RET['textarea'] as $field)
			{
				echo '<TR><TD><b>'.ParseMLField($field['TITLE']).'</b></TD><TD>';
				echo _makeTextareaInput('CUSTOM_'.$field['ID']);
				echo '</TD></TR>';
			}
		}

		if ( !$_REQUEST['category_id'] || $_REQUEST['category_id']=='1')
		{
			echo '<TR><TD><b>'._('Grade Level').'</b></TD><TD>';
			$gradelevels_RET = DBGet(DBQuery("SELECT ID,TITLE FROM SCHOOL_GRADELEVELS WHERE SCHOOL_ID='".UserSchool()."' ORDER BY SORT_ORDER"));
			$options = array();
			if (count($gradelevels_RET))
			{
				foreach ( (array)$gradelevels_RET as $gradelevel)
					$options[$gradelevel['ID']] = $gradelevel['TITLE'];
			}
			echo _makeSelectInput('GRADE_ID',$options);
			echo '</TD></TR>';

			echo '<TR><TD><b>'._('Rolling / Retention Options').'</b></TD><TD>';
			$schools_RET = DBGet(DBQuery("SELECT ID,TITLE FROM SCHOOLS WHERE ID!='".UserSchool()."' AND SYEAR='".UserSyear()."'"));
			$options = array(UserSchool()=>_('Next grade at current school'),'0'=>_('Retain'),'-1'=>_('Do not enroll after this school year'));
			if (count($schools_RET))
			{
				foreach ( (array)$schools_RET as $school)
					$options[$school['ID']] = $school['TITLE'];
			}
			echo _makeSelectInput('NEXT_SCHOOL',$options);
			echo '</TD></TR>';

			echo '<TR><TD><b>'._('Calendar').'</b></TD><TD>';
			$calendars_RET = DBGet(DBQuery("SELECT CALENDAR_ID,DEFAULT_CALENDAR,TITLE FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' ORDER BY DEFAULT_CALENDAR ASC"));
			$options = array();
			if (count($calendars_RET))
			{
				foreach ( (array)$calendars_RET as $calendar)
					$options[$calendar['CALENDAR_ID']] = $calendar['TITLE'];
			}
			echo _makeSelectInput('CALENDAR_ID',$options);
			echo '</TD></TR>';

			echo '<TR><TD><b>'._('Attendance Start Date this School Year').'</b></TD><TD>';
			$options_RET = DBGet(DBQuery("SELECT ID,TITLE AS TITLE FROM STUDENT_ENROLLMENT_CODES WHERE SYEAR='".UserSyear()."' AND TYPE='Add' ORDER BY SORT_ORDER"));
			if ( $options_RET)
			{
				foreach ( (array)$options_RET as $option)
					$add_codes[$option['ID']] = $option['TITLE'];
			}
			echo '<div class="nobr">'._makeDateInput('START_DATE').' - '._makeSelectInput('ENROLLMENT_CODE',$add_codes).'</div>';
			echo '</TD></TR>';
		}

		echo '</TABLE><BR />';

		$radio_count = count($fields_RET['radio']);
		if ( $radio_count)
		{
			echo '<TABLE class="widefat cellspacing-0 cellpadding-5 center"><TR>';
			for($i=1;$i<=$radio_count;$i++)
			{
				echo '<TD>'._makeCheckboxInput('CUSTOM_'.$fields_RET['radio'][$i]['ID'],'<b>'.ParseMLField($fields_RET['radio'][$i]['TITLE']).'</b>').'</TD>';
				if ( $i%5==0 && $i!=$radio_count)
					echo '</TR><TR>';
			}
			echo '</TR></TABLE>';
		}

		echo '<BR />';
	}

	//Widgets('activity');
	//Widgets('course');
	//Widgets('absences');

	$extra['functions'] = array('CHECKBOX'=>'_makeChooseCheckbox');
	$extra['columns_before'] = array('CHECKBOX'=>'</A><INPUT type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.form.controller.checked,\'student\');"><A>');
	$extra['new'] = true;

	Search('student_id',$extra);
	if ( $_REQUEST['search_modfunc']=='list')
		echo '<BR /><div class="center">' . SubmitButton( _( 'Save' ) ) . '</div></FORM>';
}

function _makeChooseCheckbox($value,$title='')
{	global $THIS_RET;

	return '<INPUT type="checkbox" name="student['.$THIS_RET['STUDENT_ID'].']" value="Y">';
}

function _makeTextInput($column,$numeric=false)
{
	if ( $numeric===true)
		$options = 'size=3 maxlength=11';
	else
		$options = 'size=20';

	return TextInput('','values['.$column.']','',$options);
}

function _makeTextareaInput($column,$numeric=false)
{
	return TextAreaInput('','values['.$column.']');
}

function _makeDateInput($column)
{
	return DateInput('','values['.$column.']','');
}

function _makeSelectInput($column,$options)
{
	return SelectInput('','values['.$column.']','',$options,_('N/A'),"style='max-width:190px;'");
}

function _makeCheckboxInput($column,$name)
{
	return CheckboxInput('','values['.$column.']',$name,'',true);
}
