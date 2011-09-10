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
 * @version    $Id: FitBoundingBoxVertically.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/** Internally used classes */





/** Zend_Pdf_Destination_Explicit */


/**
 * Zend_Pdf_Destination_FitBoundingBoxVertically explicit detination
 *
 * Destination array: [page /FitBV left]
 *
 * (PDF 1.1) Display the page designated by page, with the horizontal coordinate
 * left positioned at the left edge of the window and the contents of the page
 * magnified just enough to fit the entire height of its bounding box within the
 * window.
 *
 * @package    Zend_Pdf
 * @subpackage Destination
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Pdf_Destination_FitBoundingBoxVertically extends Zend_Pdf_Destination_Explicit
{
    /**
     * Create destination object
     *
     * @param Zend_Pdf_Page|integer $page  Page object or page number
     * @param float $left  Left edge of displayed page
     * @return Zend_Pdf_Destination_FitBoundingBoxVertically
     * @throws Zend_Pdf_Exception
     */
    public static function create($page, $left)
    {
        $destinationArray = new Zend_Pdf_Element_Array();

        if ($page instanceof Zend_Pdf_Page) {
            $destinationArray->items[] = $page->getPageDictionary();
        } else if (is_integer($page)) {
            $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($page);
        } else {
            
            throw new Zend_Pdf_Exception('Page entry must be a Zend_Pdf_Page object or a page number.');
        }

        $destinationArray->items[] = Zend_Pdf_Element_Name::getInstance('FitBV');
        $destinationArray->items[] = Zend_Pdf_Element_Numeric::getInstance($left);

        return new Zend_Pdf_Destination_FitBoundingBoxVertically($destinationArray);
    }

    /**
     * Get left edge of the displayed page
     *
     * @return float
     */
    public function getLeftEdge()
    {
        return $this->_destinationArray->items[2]->value;
    }

    /**
     * Set left edge of the displayed page
     *
     * @param float $left
     * @return Zend_Pdf_Action_FitBoundingBoxVertically
     */
    public function setLeftEdge($left)
    {
        $this->_destinationArray->items[2] = Zend_Pdf_Element_Numeric::getInstance($left);
        return $this;
    }

}
