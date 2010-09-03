<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009-2010
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id$
 */


class CeAccess extends Backend
{

	/**
	 * Return all content elements as array
	 *
	 * @access	public
	 * @return	array
	 */
	public function getContentElements()
	{
		$arrElements = array();

		foreach ($GLOBALS['TL_CTE'] as $k=>$v)
		{
			foreach (array_keys($v) as $kk)
			{
				$arrElements[] = $kk;
			}
		}

		return $arrElements;
	}
	
	
	/**
	 * Remove available content elements
	 *
	 * @access	public
	 * @param	object
	 * @return	void
	 */
	public function filterContentElements($dc)
	{
		if ($this->Input->get('act') == '' || $this->Input->get('act') == 'select')
			return;
			
		$this->Import('BackendUser', 'User');
		
		if ($this->User->isAdmin)
			return;
		
		$arrElements = deserialize($this->User->contentelements);
		
		if (!is_array($arrElements))
			$arrElements = array();
		
		$arrGroups = $this->User->groups;
		if (count($arrGroups) > 0)
		{
			$objGroups = $this->Database->execute("SELECT * FROM tl_user_group WHERE id IN (" . implode(',', $arrGroups) . ")");
			
			while( $objGroups->next() )
			{
				$arrGroupElements = deserialize($objGroups->contentelements);
				
				if (is_array($arrGroupElements))
				{
					$arrElements = array_merge($arrElements, $arrGroupElements);
				}
			}
		}
		
		array_unique($arrElements);
		$this->User->contentelements = $arrElements;
		
		foreach( $arrElements as $element )
		{
			foreach( $GLOBALS['TL_CTE'] as $group => $v )
			{
				if (isset($GLOBALS['TL_CTE'][$group][$element]))
					unset($GLOBALS['TL_CTE'][$group][$element]);
					
				if (!count($GLOBALS['TL_CTE'][$group]))
					unset($GLOBALS['TL_CTE'][$group]);
			}
		}
		
		// No content elements possible, disable new elements
		if (!count($GLOBALS['TL_CTE']))
		{
			$GLOBALS['TL_DCA']['tl_content']['config']['closed'] = true;
			$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['panelLayout'] = '';
		}
		
		// Default element has been hidden
		elseif (in_array($GLOBALS['TL_DCA']['tl_content']['fields']['type']['default'], $arrElements))
		{
			reset($GLOBALS['TL_CTE']);
			$GLOBALS['TL_DCA']['tl_content']['fields']['type']['default'] = @key(@current($GLOBALS['TL_CTE']));
			$GLOBALS['TL_DCA']['tl_content']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_content']['palettes'][@key(@current($GLOBALS['TL_CTE']))];
		}
		
		
		$session = $this->Session->getData();

		// Set allowed content element IDs (edit multiple)
		if (is_array($session['CURRENT']['IDS']) && count($session['CURRENT']['IDS']))
		{
			$session['CURRENT']['IDS'] = $this->Database->execute("SELECT id FROM tl_content WHERE id IN (" . implode(',', $session['CURRENT']['IDS']) . ") AND type NOT IN ('" . implode("','", $arrElements) . "')")->fetchEach('id');
		}

		// Set allowed clipboard IDs
		if (isset($session['CLIPBOARD']['tl_content']) && is_array($session['CLIPBOARD']['tl_content']['id']) && count($session['CLIPBOARD']['tl_content']['id']))
		{
			$session['CLIPBOARD']['tl_content']['id'] = $this->Database->execute("SELECT id FROM tl_content WHERE id IN (" . implode(',', $session['CLIPBOARD']['tl_content']['id']) . ") AND type NOT IN ('" . implode("','", $arrElements) . "')")->fetchEach('id');
		}
		
		// Overwrite session
		$this->Session->setData($session);
		
		
		if (!in_array($this->Input->get('act'), array('show', 'create', 'select', 'editAll')))
		{
			$objElement = $this->Database->prepare("SELECT * FROM tl_content WHERE id=?")
										 ->limit(1)
										  ->execute($dc->id);
									  
			if ($objElement->numRows && in_array($objElement->type, $arrElements))
			{
				$this->log('Attempt to access restricted content element "' . $objElement->type . '"', 'CeAccess filterContentElements()', TL_ACCESS);
				$this->redirect($this->Environment->script.'?act=error');
			}
		}
	}
	
	
	/**
	 * Hide buttons for disabled content elements
	 *
	 * @access	public
	 * @param	array
	 * @param	string
	 * @param	string
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function hideButton($row, $href, $label, $title, $icon, $attributes)
	{
		$this->Import('BackendUser', 'User');
		
		return ($this->User->isAdmin || !in_array($row['type'], $this->User->contentelements)) ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.specialchars($title).'"'.$attributes.'>'.$this->generateImage($icon, $label).'</a> ' : '';
	}
}
