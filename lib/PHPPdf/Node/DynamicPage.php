<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Node;

use PHPPdf\Node\Page,
    PHPPdf\Node\PageContext,
    PHPPdf\Document;

/**
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class DynamicPage extends Page
{
    private $prototype = null;
    private $currentPage = null;
    private $pages = array();
    private $pagesHistory = array();
    private $currentPageNumber = 1;
    private $nodeFormattingMap = array();
    private $numberOfPages = 0;

    public function __construct(Page $prototype = null)
    {
        $this->setPrototypePage($prototype ? $prototype : new Page());
        static::initializeTypeIfNecessary();
        $this->initialize();
    }
    
    public function markAsFormatted(Node $node)
    {
        $this->nodeFormattingMap[spl_object_hash($node)] = true;
    }
    
    public function isMarkedAsFormatted(Node $node)
    {
        return isset($this->nodeFormattingMap[spl_object_hash($node)]);
    }

    public function getBoundary()
    {
        return $this->getCurrentPage()->getBoundary();
    }

    public function getCurrentPage($createIfNotExists = true)
    {
        if($createIfNotExists && $this->currentPage === null)
        {
            $this->createNextPage();
        }

        return $this->currentPage;
    }

    /**
     * @return PHPPdf\Node\Page
     */
    public function getPrototypePage()
    {
        return $this->prototype;
    }

    public function setPrototypePage(Page $page)
    {
        $this->prototype = $page;
    }

    /**
     * @return PHPPdf\Node\Page
     */
    public function createNextPage()
    {
        $this->currentPage = $this->prototype->copy();
        $this->currentPage->setContext(new PageContext($this->currentPageNumber++, $this));
        $this->pages[] = $this->currentPage;
        $this->pagesHistory[] = $this->currentPage;
        $this->numberOfPages++;

        return $this->currentPage;
    }
    
    public function getNumberOfPages()
    {
        return $this->numberOfPages;
    }

    public function copy()
    {
        $copy = parent::copy();
        $copy->prototype = $this->prototype->copy();
        $copy->reset();
        $copy->nodeFormattingMap = array();
        $copy->numberOfPages = 0;

        return $copy;
    }

    public function reset()
    {
        $this->pages = array();
        $this->currentPage = null;
        $this->currentPageNumber = 1;
        $this->numberOfPages = 0;
    }

    public function getPages()
    {
        return $this->pages;
    }
    
    public function removeAllPagesExceptsCurrent()
    {
        $this->pages = $this->currentPage ? array($this->currentPage) : array();
    }
    
    public function getAllPagesExceptsCurrent()
    {
        $pages = $this->pages;
        array_pop($pages);
        
        return $pages;
    }

    protected function doDraw(Document $document)
    {
        $tasks = array();
        foreach($this->getPages() as $page)
        {
            $childTasks = $page->getOrderedDrawingTasks($document);

            foreach($childTasks as $task)
            {
                $tasks[] = $task;
            }
        }
        
        return $tasks;
    }

    public function getAttribute($name)
    {
        return $this->getPrototypePage()->getAttribute($name);
    }

    public function setAttribute($name, $value)
    {
        foreach($this->pages as $page)
        {
            $page->setAttribute($name, $value);
        }

        return $this->getPrototypePage()->setAttribute($name, $value);
    }
    
    public function mergeEnhancementAttributes($name, array $attributes = array())
    {
        $this->prototype->mergeEnhancementAttributes($name, $attributes);
    }

    protected function getAttributeDirectly($name)
    {
        return $this->getPrototypePage()->getAttributeDirectly($name);
    }

    public function getWidth()
    {
        return $this->getPrototypePage()->getWidth();
    }

    public function getHeight()
    {
        return $this->getPrototypePage()->getHeight();
    }

    protected function getHeader()
    {
        return $this->getPrototypePage()->getHeader();
    }

    protected function getFooter()
    {
        return $this->getPrototypePage()->getFooter();
    }

    public function setHeader(Container $header)
    {
        return $this->getPrototypePage()->setHeader($header);
    }

    public function setFooter(Container $footer)
    {
        return $this->getPrototypePage()->setFooter($footer);
    }

    public function setWatermark(Container $watermark)
    {
        return $this->getPrototypePage()->setWatermark($watermark);
    }

    protected function beforeFormat(Document $document)
    {
        $gc = $this->getGraphicsContextFromSourceDocument($document);
        if($gc)
        {
            $this->setPageSize($gc->getWidth().':'.$gc->getHeight());
        }

        $this->getPrototypePage()->prepareTemplate($document);
    }

    public function getDiagonalPoint()
    {
        return $this->getPrototypePage()->getDiagonalPoint();
    }

    public function getFirstPoint()
    {
        return $this->getPrototypePage()->getFirstPoint();
    }
    
    protected function getDataForSerialize()
    {
        $data = parent::getDataForSerialize();
        $data['prototype'] = $this->prototype;
        
        return $data;
    }
    
    protected function setDataFromUnserialize(array $data)
    {
        parent::setDataFromUnserialize($data);
        
        $this->prototype = $data['prototype'];
    }

    public function flush()
    {
        foreach($this->pages as $page)
        {
            $page->flush();
        }
        
        $this->pages = array();        
        $this->currentPage = null;

        parent::flush();
    }
    
    public function getUnorderedDrawingTasks(Document $document)
    {
        $tasks = array();
        
        foreach($this->getPages() as $page)
        {
            $tasks = array_merge($tasks, $page->getUnorderedDrawingTasks($document));
        }
        
        return $tasks;
    }
    
    public function getPostDrawingTasks(Document $document)
    {
        $tasks = array();
        
        foreach($this->pagesHistory as $page)
        {
            $tasks = array_merge($tasks, $page->getPostDrawingTasks($document));
        }
        
        return $tasks;
    }
}