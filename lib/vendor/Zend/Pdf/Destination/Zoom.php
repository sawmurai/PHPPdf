<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Pdf
 * @subpackage Destination
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Zoom.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/** Internally used classes */






/** Zend_Pdf_Destination_Explicit */


/**
 * Zend_Pdf_Destination_Zoom explicit detination
 *
 * Destination array: [page /XYZ left top zoom]
 *
 * Display the page designated by page, with the coordinates (left, top) positioned
 * at the upper-left corner of the window and the contents of the page
 * magnified by the factor zoom. A null value for any of the parameters left, top,
 * or zoom specifies that the current value of that parameter is to be retained unchanged.
 * A zoom value of 0 has the same meaning as a null value.
 *
 * @package    Zend_Pdf
 * @subpackage Destination
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Pdf_Destination_Zoom extends Zend_Pdf_Destination_Explicit
{
    /**
     * Create destination object
     *
     * @param Zend_Pdf_Page|integer $page  Page object or page number
     * @param float $left  Left edge of displayed page
     * @param float $top   Top edge of displayed page
     * @param float $zoom  Zoom factor
     * @return Zend_Pdf_Destination_Zoom
     * @throws Zend_Pdf_Exception
     */
    public static function create($page, $left = null, $top = null, $zoom = null)
    {
        $destinationArray = new Zend_Pdf_Element_Array();

        if ($page instanceof Zend_Pdf_Page) {
            $destinationArray->items[] = $page->getPageDictionary();
        } else if (is_integer($page)) {
            $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($page);
        } else {
            
            throw new Zend_Pdf_Exception('Page entry must be a Zend_Pdf_Page object or a page number.');
        }

        $destinationArray->items[] = Zend_Pdf_Element_Name::getInstance('XYZ');

        if ($left === null) {
            $destinationArray->items[] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($left);
        }

        if ($top === null) {
            $destinationArray->items[] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($top);
        }

        if ($zoom === null) {
            $destinationArray->items[] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($zoom);
        }

        return new Zend_Pdf_Destination_Zoom($destinationArray);
    }

    /**
     * Get left edge of the displayed page (null means viewer application 'current value')
     *
     * @return float
     */
    public function getLeftEdge()
    {
        return $this->_destinationArray->items[2]->value;
    }

    /**
     * Set left edge of the displayed page (null means viewer application 'current value')
     *
     * @param float $left
     * @return Zend_Pdf_Action_Zoom
     */
    public function setLeftEdge($left)
    {
        if ($left === null) {
            $this->_destinationArray->items[2] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $this->_destinationArray->items[2] = Zend_Pdf_Element_Numeric::getInstance($left);
        }

        return $this;
    }

    /**
     * Get top edge of the displayed page (null means viewer application 'current value')
     *
     * @return float
     */
    public function getTopEdge()
    {
        return $this->_destinationArray->items[3]->value;
    }

    /**
     * Set top edge of the displayed page (null means viewer application 'current viewer')
     *
     * @param float $top
     * @return Zend_Pdf_Action_Zoom
     */
    public function setTopEdge($top)
    {
        if ($top === null) {
            $this->_destinationArray->items[3] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $this->_destinationArray->items[3] = Zend_Pdf_Element_Numeric::getInstance($top);
        }

        return $this;
    }

    /**
     * Get ZoomFactor of the displayed page (null or 0 means viewer application 'current value')
     *
     * @return float
     */
    public function getZoomFactor()
    {
        return $this->_destinationArray->items[4]->value;
    }

    /**
     * Set ZoomFactor of the displayed page (null or 0 means viewer application 'current viewer')
     *
     * @param float $zoom
     * @return Zend_Pdf_Action_Zoom
     */
    public function setZoomFactor($zoom)
    {
        if ($zoom === null) {
            $this->_destinationArray->items[4] = Zend_Pdf_Element_Null::getInstance();
        } else {
            $this->_destinationArray->items[4] = Zend_Pdf_Element_Numeric::getInstance($zoom);
        }

        return $this;
    }
}
