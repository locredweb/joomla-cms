<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Form
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('JPATH_BASE') or die;
jimport('joomla.html.html');
jimport('joomla.access.access');
jimport('joomla.form.formfield');
/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Framework
 * @subpackage	Form
 * @since		1.6
 */
class JFormFieldRules extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	public $type = 'Rules';

	/**
	 * Method to get the field input markup.
	 *
	 * TODO: Add access check.
	 *
	 * @return	string	The field input markup.
	 * @since	1.6
	 */
	protected function getInput()
	{
		// Initialise some field attributes.
		$section	= $this->element['section'] ? (string) $this->element['section'] : '';
		$component	= $this->element['component'] ? (string) $this->element['component'] : '';
		$assetField	= $this->element['asset_field'] ? (string) $this->element['asset_field'] : 'asset_id';

		// Get the actions for the asset.
		$actions	= JAccess::getActions($component, $section);

		// Iterate over the children and add to the actions.
		foreach ($this->element->children() as $el)
		{
			if ($el->getName() == 'action') {
				$actions[] = (object) array(
					'name'			=> (string) $el['name'],
					'title'			=> (string) $el['title'],
					'description'	=> (string) $el['description']
				);
			}
		}

		// Get the explicit rules for this asset.
		if ($section == 'component') {
			// Need to find the asset id by the name of the component.
			$db = JFactory::getDbo();
			$db->setQuery('SELECT id FROM #__assets WHERE name = ' . $db->quote($component));
			$assetId = (int) $db->loadResult();

			if ($error = $db->getErrorMsg()) {
				JError::raiseNotice(500, $error);
			}
		}
		else {
			// Find the asset id of the content.
			// Note that for global configuration, com_config injects asset_id = 1 into the form.
			$assetId = $this->form->getValue($assetField);
		}

		// Use the compact form for the content rules (to be deprecated).
//		if (!empty($component) && $section != 'component') {
//			return JHtml::_('rules.assetFormWidget', $actions, $assetId, $assetId ? null : $component, $this->name, $this->id);
//		}

		//
		// Full width format.
		//

		// Get the rules for just this asset (non-recursive).
		$assetRules = JAccess::getAssetRules($assetId);

		// Get the available user groups.
		$groups = $this->getUserGroups();

		// Build the form control.
		$curLevel = 0;

		// Prepare output
		$html = array();
		$html[] = '<div id="permissions-sliders" class="pane-sliders">';
		$html[] = '<ul id="rules">';

		// Start a row for each user group.
		foreach ($groups as $group)
		{
			$difLevel = $group->level - $curLevel;

			if ($difLevel > 0) {
				$html[] = '<ul>';
			}
			else if ($difLevel < 0) {
				$html[] = str_repeat('</li></ul>', -$difLevel);
			}

			$html[] = '<li>';

			$html[] = '<div class="panel">';
			$html[] =	'<h3 class="jpane-toggler title" ><a href="javascript:void(0);"><span>';
			$html[] =	str_repeat('<span class="level">|&ndash;</span> ', $curLevel = $group->level) . $group->text;
			$html[] =	'</span></a></h3>';
			$html[] =	'<div class="jpane-slider content">';
			$html[] =		'<div class="mypanel">';
			$html[] =			'<table class="group-rules">';
			$html[] =				'<caption>' . JText::sprintf('JGROUP', $group->text) . '<br /><span>' .
									 JText::_('JRULE_SETTINGS_DESC') . '</span></caption>';
			$html[] =				'<thead>';
			$html[] =					'<tr>';

			$html[] =						'<th class="actions" id="actions-th' . $group->value . '">';
			$html[] =							'<span class="acl-action">' . JText::_('JACTION_ACTION') . '</span>';
			$html[] =						'</th>';

			$html[] =						'<th class="settings" id="settings-th' . $group->value . '">';
			$html[] =							'<span class="acl-action">' . JText::_('JRULE_SELECT_SETTING') . '</span>';
			$html[] =						'</th>';

			// The calculated setting is not shown for the root group of global configuration.
			$canCalculateSettings = ($group->parent_id || !empty($component));
			if ($canCalculateSettings) {
				$html[] =					'<th id="aclactionth' . $group->value . '">';
				$html[] =						'<span class="acl-action">' . JText::_('JRULE_CALCULATED_SETTING') . '</span>';
				$html[] =					'</th>';
			}

			$html[] =					'</tr>';
			$html[] =				'</thead>';
			$html[] =				'<tbody >';

			foreach ($actions as $action)
			{
				$html[] =				'<tr>';
				$html[] =					'<td headers="actions-th' . $group->value . '">';
				$html[] =						'<label for="' . $this->id . '_' . $action->name . '_' . $group->value . '">';
				$html[] =						JText::_($action->title);
				$html[] =						'</label>';
				$html[] =					'</td>';

				$html[] =					'<td headers="settings-th' . $group->value . '">';

				$html[] = '<select name="' . $this->name . '[' . $action->name . '][' . $group->value . ']" id="' . $this->id . '_' . $action->name . '_' . $group->value . '" title="' . JText::sprintf('JSELECT_ALLOW_DENY_GROUP', JText::_($action->title), trim($group->text)) . '">';

				$inheritedRule	= JAccess::checkGroup($group->value, $action->name, $assetId);

				// Get the actual setting for the action for this group.
				$assetRule		= $assetRules->allow($action->name, $group->value);

				// Build the dropdowns for the permissions sliders

				// The parent group has "Not Set", all children can rightly "Inherit" from that.
				$html[] = '<option value=""' . ($assetRule === null ? ' selected="selected"' : '') . '>' .
							JText::_(empty($group->parent_id) && empty($component) ? 'JRULE_NOT_SET' : 'JRULE_INHERITED') . '</option>';
				$html[] = '<option value="1"' . ($assetRule === true ? ' selected="selected"' : '') . '>' .
							JText::_('JRULE_ALLOWED') . '</option>';
				$html[] = '<option value="0"' . ($assetRule === false ? ' selected="selected"' : '') . '>' .
							JText::_('JRULE_DENIED') . '</option>';

				$html[] = '</select>&nbsp; ';

				// If this asset's rule is allowed, but the inherited rule is deny, we have a conflict.
				if (($assetRule === true) && ($inheritedRule === false)) {
					$html[] = JText::_('JRULE_CONFLICT');
				}

				$html[] = '</td>';

				// Build the Calculated Settings column.
				// The inherited settings column is not displayed for the root group in global configuration.
				if ($canCalculateSettings) {
					$html[] = '<td headers="global_th' . $group->value . '">';

					// This is where we show the current effective settings considering currrent group, path and cascade.
					// Check whether this is a component or global. Change the text slightly.

					if (JAccess::checkGroup($group->value, 'core.admin') !== true)
					{
						if ($inheritedRule === null) {
							$html[] = '<span class="icon-16-unset">'.
										JText::_('JRULE_NOT_ALLOWED').'</span>';
						}
						else if ($inheritedRule === true)
						{
							$html[] = '<span class="icon-16-allowed">'.
										JText::_('JRULE_ALLOWED').'</span>';
						}
						else if ($inheritedRule === false) {
							if ($assetRule === false) {
								$html[] = '<span class="icon-16-denied">'.
											JText::_('JRULE_NOT_ALLOWED').'</span>';
							}
							else {
								$html[] = '<span class="icon-16-denied"><span class="icon-16-locked">'.
											JText::_('JRULE_NOT_ALLOWED_LOCKED').'</span></span>';
							}
						}

						//Now handle the groups with core.admin who always inherit an allow.
					}
					else if (!empty($component)) {
						$html[] = '<span class="icon-16-allowed"><span class="icon-16-locked">'.
									JText::_('JRULE_ALLOWED_ADMIN').'</span></span>';
					}
					else {
						// Special handling for  groups that have global admin because they can't  be denied.
						// The admin rights can be changed.
						if ($action->name === 'core.admin') {
							$html[] = '<span class="icon-16-allowed">'.
										JText::_('JRULE_ALLOWED').'</span>';
						}
						elseif ($inheritedRule === false) {
							// Other actions cannot be changed.
							$html[] = '<span class="icon-16-denied"><span class="icon-16-locked">'.
										JText::_('JRULE_NOT_ALLOWED_ADMIN_CONFLICT').'</span></span>';
						}
						else {
							$html[] = '<span class="icon-16-allowed"><span class="icon-16-locked">'.
										JText::_('JRULE_ALLOWED_ADMIN').'</span></span>';
						}
					}

					$html[] = '</td>';
				}

				$html[] = '</tr>';
			}

			$html[] = '</tbody>';
			$html[] = '</table></div>';
			$html[] = JText::_('JRULE_SETTING_NOTES');
			$html[] = '</div></div>';

		} // endforeach

		$html[] = str_repeat('</li></ul>', $curLevel);
		$html[] = '</ul>';
		$html[] = '</div>';

		$js = "window.addEvent('domready', function(){ new Accordion($$('div#permissions-sliders.pane-sliders .panel h3.jpane-toggler'), $$('div#permissions-sliders.pane-sliders .panel div.jpane-slider'), {onActive: function(toggler, i) {toggler.addClass('jpane-toggler-down');toggler.removeClass('jpane-toggler');Cookie.write('jpanesliders_permissions-sliders".$component."',$$('div#permissions-sliders.pane-sliders .panel h3').indexOf(toggler));},onBackground: function(toggler, i) {toggler.addClass('jpane-toggler');toggler.removeClass('jpane-toggler-down');},duration: 300,display: ".JRequest::getInt('jpanesliders_permissions-sliders'.$component, 0, 'cookie').",show: ".JRequest::getInt('jpanesliders_permissions-sliders'.$component, 0, 'cookie').",opacity: false}); });";

		JFactory::getDocument()->addScriptDeclaration($js);

		return implode("\n", $html);
	}

	/**
	 * Get a list of the user groups.
	 *
	 * @return	array
	 * @since	1.6
	 */
	protected function getUserGroups()
	{
		// Initialise variables.
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true)
			->select('a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level, a.parent_id')
			->from('#__usergroups AS a')
			->leftJoin('`#__usergroups` AS b ON a.lft > b.lft AND a.rgt < b.rgt')
			->group('a.id')
			->order('a.lft ASC');

		$db->setQuery($query);
		$options = $db->loadObjectList();

		return $options;
	}
}
