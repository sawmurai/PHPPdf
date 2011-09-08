<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Node;

use PHPPdf\Document,
    PHPPdf\Util,
    PHPPdf\Node\Container,
    PHPPdf\Util\Boundary,
    PHPPdf\Util\DrawingTask,
    PHPPdf\Enhancement\EnhancementBag,
    PHPPdf\Formatter\Formatter,
    PHPPdf\Node\Behaviour\Behaviour,
    PHPPdf\Exception\InvalidAttributeException,
    PHPPdf\Util\Point;

/**
 * Base node class
 *
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
abstract class Node implements Drawable, NodeAware, \ArrayAccess, \Serializable
{
    const MARGIN_AUTO = 'auto';
    const FLOAT_NONE = 'none';
    const FLOAT_LEFT = 'left';
    const FLOAT_RIGHT = 'right';
    const ALIGN_LEFT = 'left';
    const ALIGN_RIGHT = 'right';
    const ALIGN_CENTER = 'center';
    const ALIGN_JUSTIFY = 'justify';
    const VERTICAL_ALIGN_TOP = 'top';
    const VERTICAL_ALIGN_MIDDLE = 'middle';
    const VERTICAL_ALIGN_BOTTOM = 'bottom';
    const TEXT_DECORATION_NONE = 'none';
    const TEXT_DECORATION_UNDERLINE = 'underline';
    const TEXT_DECORATION_LINE_THROUGH = 'line-through';
    const TEXT_DECORATION_OVERLINE = 'overline';
    const ROTATE_DIAGONALLY = 'diagonally';
    const ROTATE_OPPOSITE_DIAGONALLY = '-diagonally';

    private static $attributeSetters = array();
    private static $attributeGetters = array();
    private static $initialized = array();

    private $attributes = array();
    private $attributesSnapshot = null;
    private $priority = 0;

    private $parent = null;
    private $hadAutoMargins = false;
    private $relativeWidth = null;

    private $boundary = null;

    private $enhancements = array();
    private $enhancementBag = null;
    private $drawingTasks = array();
    private $formattersNames = array();
    
    private $behaviours = array();
    
    private $ancestorWithRotation = null;
    private $ancestorWithFontSize = null;

    public function __construct(array $attributes = array())
    {
        $this->boundary = new Boundary();

        static::initializeTypeIfNecessary();

        $this->initialize();
        $this->setAttributes($attributes);
    }
    
    protected final static function initializeTypeIfNecessary()
    {
        $class = get_called_class();
        if(!isset(self::$initialized[$class]))
        {
            static::initializeType();
            self::$initialized[$class] = true;
        }
    }

    protected static function initializeType()
    {
        //TODO refactoring
        $attributeWithGetters = array('width', 'height', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom', 'font-type', 'font-size', 'float', 'breakable');
        $attributeWithSetters = array('width', 'height', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'font-type', 'float', 'static-size', 'font-size', 'margin', 'padding', 'break', 'breakable', 'dump');

        $predicateGetters = array('breakable');
        
        $attributeWithGetters = array_flip($attributeWithGetters);
        array_walk($attributeWithGetters, function(&$value, $key) use($predicateGetters){
            $method = in_array($key, $predicateGetters) ? 'is' : 'get';
            $value = $method.str_replace('-', '', $key);
        });
        
        $attributeWithSetters = array_flip($attributeWithSetters);
        array_walk($attributeWithSetters, function(&$value, $key){
            $value = 'set'.str_replace('-', '', $key);
        });
        
        static::setAttributeGetters($attributeWithGetters);
        static::setAttributeSetters($attributeWithSetters);
    }
    
    /**
     * @todo refactoring
     */
    protected final static function setAttributeGetters(array $getters)
    {
        $class = get_called_class();
        if(!isset(self::$attributeGetters[$class]))
        {
            self::$attributeGetters[$class] = array();
        }

        self::$attributeGetters[$class] = $getters + self::$attributeGetters[$class];
    }
    
    protected final static function setAttributeSetters(array $setters)
    {
        $class = get_called_class();
        if(!isset(self::$attributeSetters[$class]))
        {
            self::$attributeSetters[$class] = array();
        }
        
        self::$attributeSetters[$class] = $setters + self::$attributeSetters[$class];
    }

    protected function addDrawingTasks(array $tasks)
    {
        foreach($tasks as $task)
        {
            $this->addDrawingTask($task);
        }
    }
    
    protected function addDrawingTask(DrawingTask $task)
    {
        $this->drawingTasks[] = $task;
    }

    /**
     * Add enhancement attributes, if enhancement with passed name is exists, it will be
     * merged.
     * 
     * @param string $name Name of enhancement
     * @param array $attributes Attributes of enhancement
     */
    public function mergeEnhancementAttributes($name, array $attributes = array())
    {
        $this->enhancementBag->add($name, $attributes);
    }

    /**
     * Get all enhancement data or data of enhancement with passed name
     * 
     * @param string $name Name of enhancement to get
     * @return array If $name is null, data of all enhancements will be returned, otherwise data of enhancement with passed name will be returned.
     */
    public function getEnhancementsAttributes($name = null)
    {
        if($name === null)
        {
            return $this->enhancementBag->getAll();
        }

        return $this->enhancementBag->get($name);
    }

    /**
     * @return array Array of Enhancement objects
     */
    public function getEnhancements()
    {
        return $this->enhancements;
    }

    /**
     * @return PHPPdf\Util\Boundary
     */
    public function getBoundary()
    {
        return $this->boundary;
    }
    
    /**
     * @return PHPPdf\Util\Boundary Boundary with no translated points by margins, paddings etc.
     */
    public function getRealBoundary()
    {
        return $this->getBoundary();
    }

    protected function setBoundary(Boundary $boundary)
    {
        $this->boundary = $boundary;
    }

    /**
     * Gets point of left upper corner of this node or null if boundaries have not been
     * calculated yet.
     *
     * @return PHPPdf\Util\Point
     */
    public function getFirstPoint()
    {
        return $this->getBoundary()->getFirstPoint();
    }
    
    /**
     * Gets point of left upper corner of this node, this method works on boundary from {@see getRealBoundary()}
     * on contrast to {@see getFirstPoint()} method.
     * 
     * @return PHPPdf\Util\Point
     */
    public function getRealFirstPoint()
    {
        return $this->getRealBoundary()->getFirstPoint();
    }

    /**
     * Get point of right bottom corner of this node or null if boundaries have not been
     * calculated yet.
     *
     * @return PHPPdf\Util\Point
     */
    public function getDiagonalPoint()
    {
        return $this->getBoundary()->getDiagonalPoint();
    }
    
    /**
     * Gets point of right bottom corner of this node, this method works on boundary from {@see getRealBoundary()}
     * on contrast to {@see getDiagonalPoint()} method.
     * 
     * @return PHPPdf\Util\Point
     */
    public function getRealDiagonalPoint()
    {
        return $this->getRealBoundary()->getDiagonalPoint();
    }
    
    /**
     * @return PHPPdf\Util\Point Point that divides line between first and diagonal points on half
     */
    public function getMiddlePoint()
    {
        return $this->getBoundary()->getMiddlePoint();
    }

    public function setParent(Container $node)
    {
        $oldParent = $this->getParent();
        if($oldParent)
        {
            $oldParent->remove($this);
        }

        $this->parent = $node;

        return $this;
    }

    /**
     * @return Node
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Gets ancestor with passed type. If ancestor has not been found, null will be returned.
     * 
     * @param string $type Full class name with namespace
     * @return PHPPdf\Node\Node Nearest ancestor in $type
     */
    public function getAncestorByType($type)
    {
        $current = $this;
        do
        {
            $parent = $current->getParent();
            $current = $parent;
        }
        while($parent && !$parent instanceof $type);

        return $parent;
    }

    /**
     * @return array Siblings with current object includes current object
     */
    public function getSiblings()
    {
        $parent = $this->getParent();

        if(!$parent)
        {
            return array();
        }

        return $parent->getChildren();
    }

    public function initialize()
    {
        $this->addAttribute('width', null);
        $this->addAttribute('height', null);

        $this->addAttribute('min-width', 0);

        $this->addAttribute('margin-top');
        $this->addAttribute('margin-left');
        $this->addAttribute('margin-right');
        $this->addAttribute('margin-bottom');

        $this->addAttribute('margin');
        $this->addAttribute('padding');

        $this->addAttribute('font-type');
        $this->addAttribute('font-size');

        $this->addAttribute('color');

        $this->addAttribute('padding-top', 0);
        $this->addAttribute('padding-right', 0);
        $this->addAttribute('padding-bottom', 0);
        $this->addAttribute('padding-left', 0);
        $this->addAttribute('breakable', true);

        $this->addAttribute('line-height');
        $this->addAttribute('text-align', null);

        $this->addAttribute('float', self::FLOAT_NONE);
        $this->addAttribute('font-style', null);
        $this->addAttribute('static-size', false);
        $this->addAttribute('break', false);
        
        $this->addAttribute('vertical-align', null);
        
        $this->addAttribute('text-decoration', null);
        
        $this->addAttribute('dump', false);
        
        $this->addAttribute('alpha', null);
        $this->addAttribute('rotate', null);

        $this->setEnhancementBag(new EnhancementBag());
    }
    
    protected function setEnhancementBag(EnhancementBag $bag)
    {
        $this->enhancementBag = $bag;
    }
    
    public function addBehaviour(Behaviour $behaviour)
    {
        $this->behaviours[] = $behaviour;
    }
    
    /**
     * @return array Array of Behhaviour objects
     */
    public function getBehaviours()
    {
        return $this->behaviours;
    }

    /**
     * Reset state of object
     */
    public function reset()
    {
    }

    /**
     * @return Page Page of current objects
     * @throws LogicException If object has not been attached to any page
     */
    public function getPage()
    {
        $page = $this->getAncestorByType('\PHPPdf\Node\Page');

        if(!$page)
        {
            throw new \LogicException(sprintf('Node "%s" is not attach to any page.', get_class($this)));
        }

        return $page;
    }

    /**
     * Gets font object associated with current object
     * 
     * @return Font
     */
    public function getFont(Document $document)
    {
        $fontType = $this->getRecurseAttribute('font-type');

        if($fontType)
        {
            $font = $document->getFont($fontType);
            
            $fontStyle = $this->getRecurseAttribute('font-style');
            if($fontStyle)
            {
                $font->setStyle($fontStyle);
            }

            return $font;
        }

        return null;
    }
    
    public function setFloat($float)
    {
        $this->setAttributeDirectly('float', $float);
    }
       
    public function getFloat()
    {
        return $this->getAttributeDirectly('float');
    }
    
    public function setFontType($fontType)
    {
        $this->setAttributeDirectly('font-type', $fontType);
    }

    public function getFontType($recurse = false)
    {
        if(!$recurse)
        {
            return $this->getAttributeDirectly('font-type');
        }
        else
        {
            return $this->getRecurseAttribute('font-type');
        }
    }
    
    public function getFontSize()
    {
        return $this->getAttributeDirectly('font-size');
    }
    
    public function getFontSizeRecursively()
    {
        $ancestor = $this->getAncestorWithFontSize();
        
        return $ancestor === false ? $this->getFontSize() : $ancestor->getFontSize();
    }
    
    public function getLineHeightRecursively()
    {
        $ancestor = $this->getAncestorWithFontSize();
        
        return $ancestor === false ? $this->getAttribute('line-height') : $ancestor->getAttribute('line-height');
    }
    
    public function getTextDecorationRecursively()
    {
        return $this->getRecurseAttribute('text-decoration');
    }

    /**
     * Set target width
     *
     * @param int|null $width
     */
    public function setWidth($width)
    {
        $this->setAttributeDirectly('width', $width);

        if(\strpos($width, '%') !== false)
        {
            $this->setRelativeWidth($width);
        }

        return $this;
    }

    public function setRelativeWidth($width)
    {
        $this->relativeWidth = $width;
    }

    public function getRelativeWidth()
    {
        return $this->relativeWidth;
    }

    private function convertToInteger($value, $nullValue = null)
    {
        return ($value === null ? $nullValue : (int) $value);
    }

    public function getWidth()
    {
        return $this->getWidthOrHeight('width');
    }
    
    /**
     * @return int Real width not modified by margins, paddings etc.
     */
    public function getRealWidth()
    {
        return $this->getWidth();
    }
    
    public function getMinWidth()
    {
        return 0;
    }
    
    /**
     * @return int Real height not modified by margins, paddings etc.
     */
    public function getRealHeight()
    {
        return $this->getHeight();
    }

    private function getWidthOrHeight($sizeType)
    {
        return $this->getAttributeDirectly($sizeType);
    }

    public function getWidthWithMargins()
    {
        $width = $this->getWidth();

        $margins = $this->getMarginLeft() + $this->getMarginRight();

        return ($width + $margins);
    }

    public function getWidthWithoutPaddings()
    {
        $width = $this->getWidth();

        $paddings = $this->getPaddingLeft() + $this->getPaddingRight();

        return ($width - $paddings);
    }

    public function getHeightWithMargins()
    {
        $height = $this->getHeight();

        $margins = $this->getMarginTop() + $this->getMarginBottom();

        return ($height + $margins);
    }

    public function getHeightWithoutPaddings()
    {
        $height = $this->getHeight();

        $paddings = $this->getPaddingTop() + $this->getPaddingBottom();

        return ($height - $paddings);
    }

    /**
     * Set target height
     *
     * @param int|null $height
     */
    public function setHeight($height)
    {
        $this->setAttributeDirectly('height', $height);

        return $this;
    }

    public function getHeight()
    {
        return $this->getWidthOrHeight('height');
    }

    public function setMarginTop($margin)
    {
        return $this->setMarginAttribute('margin-top', $margin);
    }

    protected function setMarginAttribute($name, $value)
    {
        $this->setAttributeDirectly($name, $value === self::MARGIN_AUTO ? $value : $this->convertToInteger($value));

        return $this;
    }

    public function setMarginLeft($margin)
    {
        return $this->setMarginAttribute('margin-left', $margin);
    }

    public function setMarginRight($margin)
    {
        return $this->setMarginAttribute('margin-right', $margin);
    }

    public function setMarginBottom($margin)
    {
        return $this->setMarginAttribute('margin-bottom', $margin);
    }

    public function getMarginTop()
    {
        return $this->getAttributeDirectly('margin-top');
    }

    public function getMarginLeft()
    {
        return $this->getAttributeDirectly('margin-left');
    }

    public function getMarginRight()
    {
        return $this->getAttributeDirectly('margin-right');
    }

    public function getMarginBottom()
    {
        return $this->getAttributeDirectly('margin-bottom');
    }

    /**
     * @return bool|null Null if $flag !== null, true if margins was 'auto' value, otherwise false
     */
    public function hadAutoMargins($flag = null)
    {
        if($flag === null)
        {
            return $this->hadAutoMargins;
        }

        $this->hadAutoMargins = (bool) $flag;
    }

    /**
     * Setting "css style" margins
     */
    public function setMargin()
    {
        $margins = \func_get_args();

        if(count($margins) === 1 && is_string(current($margins)))
        {
            $margins = explode(' ', current($margins));
        }

        $marginLabels = array('margin-top', 'margin-right', 'margin-bottom', 'margin-left');
        $this->setComposeAttribute($marginLabels, $margins);

        return $this;
    }

    private function setComposeAttribute($attributeNames, $attributes)
    {
        $count = count($attributes);

        if($count === 0)
        {
            throw new \InvalidArgumentException('Attribute values doesn\'t pass.');
        }

        $repeat = \ceil(4 / $count);

        for($i=1; $i<$repeat; $i++)
        {
            $attributes = array_merge($attributes, $attributes);
        }

        foreach($attributeNames as $key => $label)
        {
            $this->setAttribute($label, $attributes[$key]);
        }
    }

    /**
     * Set "css style" paddings
     */
    public function setPadding()
    {
        $paddings = \func_get_args();

        if(count($paddings) === 1 && is_string(current($paddings)))
        {
            $paddings = explode(' ', current($paddings));
        }


        $paddingLabels = array('padding-top', 'padding-right', 'padding-bottom', 'padding-left');
        $this->setComposeAttribute($paddingLabels, $paddings);

        return $this;
    }
    
    public function getPaddingTop()
    {
        return $this->getAttributeDirectly('padding-top');
    }
    
    public function getPaddingBottom()
    {
        return $this->getAttributeDirectly('padding-bottom');
    }
    
    public function getPaddingLeft()
    {
        return $this->getAttributeDirectly('padding-left');
    }
    
    public function getPaddingRight()
    {
        return $this->getAttributeDirectly('padding-right');
    }
    
    public function getEncoding()
    {
        return $this->getPage()->getAttribute('encoding');
    }
    
    public function getAlpha()
    {
        return $this->getRecurseAttribute('alpha');
    }
    
    /**
     * @return float Angle of rotate in radians
     */
    public function getRotate()
    {
        $rotate = $this->getAttribute('rotate');
        if(in_array($rotate, array(self::ROTATE_DIAGONALLY, self::ROTATE_OPPOSITE_DIAGONALLY)) && ($page = $this->getPage()))
        {
            $width = $page->getWidth();
            $height = $page->getHeight();
            $d = sqrt($width*$width + $height*$height);

            $angle = $d == 0 ? 0 : acos($width/$d);
            
            if($rotate === self::ROTATE_OPPOSITE_DIAGONALLY)
            {
                $angle = -$angle;
            }
            
            $rotate = $angle;
        }

        return $rotate === null ? null : (float) $rotate;
    }
    
    public function setFontSize($size)
    {
        $this->setAttributeDirectly('font-size', (int)$size);
        $this->setAttribute('line-height', (int) ($size + $size*0.2));

        return $this;
    }

    /**
     * Sets attributes values
     * 
     * @param array $attributes Array of attributes
     * 
     * @throws InvalidAttributeException If at least one of attributes isn't supported by this node
     */
    public function setAttributes(array $attributes)
    {
        foreach($attributes as $name => $value)
        {
            $this->setAttribute($name, $value);
        }
    }

    /**
     * Sets attribute value
     * 
     * @param string $name Name of attribute
     * @param mixed $value Value of attribute
     * 
     * @throws InvalidAttributeException If attribute isn't supported by this node     * 
     * @return Node Self reference
     */
    public function setAttribute($name, $value)
    {
        $this->throwExceptionIfAttributeDosntExist($name);
        
        $class = get_class($this);
        if(isset(self::$attributeSetters[$class][$name]))
        {
            $methodName = self::$attributeSetters[$class][$name];
            $this->$methodName($value);
        }
        else
        {
            $this->setAttributeDirectly($name, $value);
        }

        return $this;
    }

    protected function setAttributeDirectly($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    protected function getAttributeDirectly($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    private function throwExceptionIfAttributeDosntExist($name)
    {
        if(!$this->hasAttribute($name))
        {
            throw new InvalidAttributeException($name);
        }
    }

    private function getAttributeMethodName($prefix, $name)
    {
        $parts = \explode('-', $name);

        return sprintf('%s%s', $prefix, \implode('', $parts));
    }

    protected function addAttribute($name, $default = null)
    {
        $this->setAttributeDirectly($name, $default);
    }
    
    public function setBreakable($flag)
    {
        $flag = $this->filterBooleanValue($flag);
        $this->setAttributeDirectly('breakable', $flag);
    }
    
    public function setDump($flag)
    {
        $flag = $this->filterBooleanValue($flag);
        $this->setAttributeDirectly('dump', $flag);
    }
    
    public function isBreakable()
    {
        try
        {
            $page = $this->getPage();
            
            if($page->getHeight() < $this->getHeight())
            {
                return true;
            }            
        }
        catch (\LogicException $e)
        {
            //ignore, original attribute value will be returned
        }
        
        return $this->getAttributeDirectly('breakable');
    }
    
    public function setStaticSize($flag)
    {
        $flag = $this->filterBooleanValue($flag);
        $this->setAttributeDirectly('static-size', $flag);
    }
    
    public function setBreak($flag)
    {
        $flag = $this->filterBooleanValue($flag);
        $this->setAttributeDirectly('break', $flag);
    }
    
    final protected function filterBooleanValue($value)
    {
        return Util::convertBooleanValue($value);
    }

    /**
     * @return bool True if attribute exeists, even if have null value, otherwise false
     */
    public function hasAttribute($name)
    {
        return in_array($name, array_keys($this->attributes));
    }

    /**
     * Returns attribute value
     * 
     * @param string $name Name of attribute
     * 
     * @throws InvalidAttributeException If attribute isn't supported by this node     * 
     * @return mixed Value of attribute
     */
    public function getAttribute($name)
    {
        $this->throwExceptionIfAttributeDosntExist($name);

        $class = get_class($this);
        $getters = self::$attributeGetters;
        if(isset(self::$attributeGetters[$class][$name]))
        {
            $methodName = self::$attributeGetters[$class][$name];
            return $this->$methodName();
        }
        else
        {
            return $this->getAttributeDirectly($name);
        }        
    }

    /**
     * Getting attribute from this node or parents. If value of attribute is null,
     * this method is recurse invoking on parent.
     */
    public function getRecurseAttribute($name)
    {
        $value = $this->getAttribute($name);
        $parent = $this->getParent();
        if($value === null && $parent)
        {
            $value = $parent->getRecurseAttribute($name);
            $this->setAttribute($name, $value);
            return $value;
        }

        return $value;
    }
    
    /**
     * Make snapshot of attribute's map
     */
    public function makeAttributesSnapshot(array $attributeNames = null)
    {
        if($attributeNames === null)
        {
            $attributeNames = array_keys($this->attributes);
        }
               
        $this->attributesSnapshot = array_intersect_key($this->attributes, array_flip($attributeNames));
    }

    /**
     * @return array|null Last made attribute's snapshot, null if snapshot haven't made.
     */
    public function getAttributesSnapshot()
    {
        return $this->attributesSnapshot;
    }

    /**
     * Returns array of PHPPdf\Util\DrawingTask objects. Those objects encapsulate drawing function.
     *
     * @return array Array of PHPPdf\Util\DrawingTask objects
     */
    public function getDrawingTasks(Document $document)
    {
        try
        {
            $this->preDraw($document);
            $this->doDraw($document);
            $this->postDraw($document);

            return $this->drawingTasks;
        }
        catch(\Exception $e)
        {
            throw new \PHPPdf\Exception\DrawingException(sprintf('Error while drawing node "%s"', get_class($this)), 0, $e);
        }
    }

    protected function preDraw(Document $document)
    {
        $tasks = $this->getDrawingTasksFromEnhancements($document);
        $this->addDrawingTasks($tasks);
    }
    
    protected function getDrawingTasksFromEnhancements(Document $document)
    {
        $tasks = array();
        
        $enhancements = $document->getEnhancements($this->enhancementBag);
        foreach($enhancements as $enhancement)
        {
            $callback = array($enhancement, 'enhance');
            $args = array($this, $document);
            $priority = $enhancement->getPriority() + $this->getPriority();
            $tasks[] = new DrawingTask($callback, $args, $priority);
        }
        
        foreach($this->behaviours as $behaviour)
        {
            $callback = function($behaviour, $node){
                $behaviour->attach($node->getGraphicsContext(), $node);
            };
            $args = array($behaviour, $this);
            $tasks[] = new DrawingTask($callback, $args);
        }
        
        return $tasks;
    }

    public function getPriority()
    {
        return $this->priority;
    }
    
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    protected function setPriorityFromParent()
    {
        $parentPriority = $this->getParent() ? $this->getParent()->getPriority() : 0;
        $this->priority = $parentPriority - 1;

        foreach($this->getChildren() as $child)
        {
            $child->setPriorityFromParent();
        }
    }

    protected function doDraw(Document $document)
    {
    }

    protected function postDraw(Document $document)
    {
        if($this->getAttribute('dump'))
        {
            $this->addDrawingTask($this->createDumpTask());
        }
    }
    
    protected function createDumpTask()
    {
        $task = new DrawingTask(function($node){
            $gc = $node->getGraphicsContext();
            $firstPoint = $node->getFirstPoint();
            $diagonalPoint = $node->getDiagonalPoint();
            
            $boundary = $node->getBoundary();
            $coordinations = array();
            foreach($boundary as $point)
            {
                $coorinations[] = $point->toArray();
            }
            
            $attributes = $node->getAttributes() + $node->getEnhancementsAttributes();
            
            $dumpText = var_export(array(
                'attributes' => $attributes,
                'coordinations' => $coorinations,
            ), true);

            $gc->attachStickyNote($firstPoint->getX(), $firstPoint->getY(), $diagonalPoint->getX(), $diagonalPoint->getY(), $dumpText);
        }, array($this));

        return $task;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function preFormat(Document $document)
    {
    }

    public function offsetExists($offset)
    {
        return $this->hasAttribute($offset);
    }

    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->setAttribute($offset, null);
    }

    public function getStartDrawingPoint()
    {
        list($x, $y) = $this->getFirstPoint()->toArray();

        return array($x + $this->getPaddingLeft(), $y - $this->getPaddingTop());
    }

    /**
     * @return Node Previous sibling node, null if previous sibling dosn't exist
     */
    public function getPreviousSibling()
    {
        $siblings = $this->getSiblings();
        for($i=0, $count = count($siblings); $i<$count && $siblings[$i] !== $this; $i++)
        {
        }

        return isset($siblings[$i-1]) ? $siblings[$i-1] : null;
    }
    
    /**
     * @deprecated To remove, getDiagonalPoint() is replacement
     */
    public function getEndDrawingPoint()
    {
        list($x, $y) = $this->getDiagonalPoint()->toArray();

        return array($x - $this->getPaddingRight(), $y + $this->getPaddingBottom());
    }

    public function getRealMarginLeft()
    {
        return $this->getMarginLeft();
    }

    public function removeParent()
    {
        $this->parent = null;
    }

    /**
     * @return Node Copy of this node
     */
    public function copy()
    {
        $copy = clone $this;
        $copy->reset();
        $copy->removeParent();
        $copy->boundary = new Boundary();
        $copy->enhancementBag = clone $this->enhancementBag;
        $copy->drawingTasks = array();

        return $copy;
    }

    final protected function __clone()
    {
    }

    /**
     * Translates position of this node
     * 
     * @param float $x X coord of translation vector
     * @param float $y Y coord of translation vector
     */
    public function translate($x, $y)
    {
        $this->getBoundary()->translate($x, $y);
    }

    /**
     * Resizes node by passed sizes
     * 
     * @param float $x Value of width's resize
     * @param float $y Value of height's resize
     */
    public function resize($x, $y)
    {
        $diagonalXCoord = $this->getDiagonalPoint()->getX() - $this->getPaddingRight();

        $this->getBoundary()->pointTranslate(1, $x, 0);
        $this->getBoundary()->pointTranslate(2, $x, $y);
        $this->getBoundary()->pointTranslate(3, 0, $y);

        foreach($this->getChildren() as $child)
        {
            $childDiagonalXCoord = $child->getDiagonalPoint()->getX() + $child->getMarginRight();

            $relativeWidth = $child->getRelativeWidth();

            if($relativeWidth !== null)
            {
                $relativeWidth = ((int) $relativeWidth)/100;
                $childResize = ($diagonalXCoord + $x) * $relativeWidth - $childDiagonalXCoord;
            }
            else
            {
                $childResize = $x + ($diagonalXCoord - $childDiagonalXCoord);
                $childResize = $childResize < 0 ? $childResize : 0;
            }

            if($childResize != 0)
            {
                $child->resize($childResize, 0);
            }
        }
    }

    /**
     * Break node at passed $height.
     *
     * @param integer $height
     * @return \PHPPdf\Node\Node|null Second node created afted breaking
     */
    public function breakAt($height)
    {
        if($this->shouldNotBeBroken($height))
        {
            return null;
        }

        return $this->doBreakAt($height);
    }
    
    protected function shouldNotBeBroken($height)
    {
        if($height <= 0 || $height >= $this->getHeight())
        {
            return true;
        }
        
        try
        {
            $page = $this->getPage();
            if($page && $page->getHeight() < $this->getHeight())
            {
                return false;
            }
        }
        catch(\LogicException $e)
        {
            //if node has no parent, breakable attribute will decide
        }
        
        return !$this->getAttribute('breakable');
    }

    protected function doBreakAt($height)
    {
        $boundary = $this->getBoundary();
        $clonedBoundary = clone $boundary;

        $trueHeight = $boundary->getFirstPoint()->getY() - $boundary->getDiagonalPoint()->getY();
        
        $heightComplement = $trueHeight - $height;

        $boundary->reset();
        $clone = $this->copy();

        $boundary->setNext($clonedBoundary[0])
                 ->setNext($clonedBoundary[1])
                 ->setNext($clonedBoundary[2]->translate(0, - $heightComplement))
                 ->setNext($clonedBoundary[3]->translate(0, - $heightComplement))
                 ->close();

        $boundaryOfClone = $clone->getBoundary();
        $boundaryOfClone->reset();

        $boundaryOfClone->setNext($clonedBoundary[0]->translate(0, $height))
                        ->setNext($clonedBoundary[1]->translate(0, $height))
                        ->setNext($clonedBoundary[2])
                        ->setNext($clonedBoundary[3])
                        ->close();

        $clone->setHeight($this->getHeight() - $height);
        $this->setHeight($height);

        return $clone;
    }

    /**
     * Adds node as child
     */
    public function add(Node $node)
    {
    }

    /**
     * Removes node from children
     * 
     * @return boolean True if node has been found and succesfully removed, otherwise false
     */
    public function remove(Node $node)
    {
        return false;
    }

    /**
     * @return array Array of Node objects
     */
    public function getChildren()
    {
        return array();
    }
    
    /**
     * @return boolean Node is able to have children?
     */
    public function isLeaf()
    {
        return false;
    }
    
    /**
     * @return boolean True if element is inline, false if block
     */
    public function isInline()
    {
        return false;
    }
    
    /**
     * Check if this node has leaf descendants.
     * 
     * If $bottomYCoord is passed, only descendants above passed coord are checked
     * 
     * @return boolean
     */
    public function hasLeafDescendants($bottomYCoord = null)
    {
        return false;
    }
    
    protected function isAbleToExistsAboveCoord($yCoord)
    {
        foreach($this->getChildren() as $child)
        {
            if($child->isAbleToExistsAboveCoord($yCoord))
            {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Gets child under passed index
     * 
     * @param integer Index of child
     * 
     * @return Node
     * @throws OutOfBoundsException Child dosn't exist
     */
    public function getChild($index)
    {
        $children = $this->getChildren();
        
        if(!isset($children[$index]))
        {
            throw new \OutOfBoundsException(sprintf('Child "%s" dosn\'t exist.', $index));
        }

        return $children[$index];
    }

    public function getNumberOfChildren()
    {
        return count($this->getChildren());
    }

    public function removeAll()
    {
    }
    
    public function convertScalarAttribute($name, $parentValue = null)
    {
        if($parentValue === null && ($parent = $this->getParent()))
        {
            $parentValue = $this->getParent()->getAttribute($name);
        }
        
        $potentiallyRelativeValue = $this->getAttribute($name);
        
        $absoluteValue = \PHPPdf\Util::convertFromPercentageValue($potentiallyRelativeValue, $parentValue);
        if($absoluteValue !== $potentiallyRelativeValue)
        {
            $this->setAttribute($name, $absoluteValue);
        }
    }

    /**
     * Format node by given formatters.
     */
    public function format(Document $document)
    {
        foreach($this->formattersNames as $formatterName)
        {
            $formatter = $document->getFormatter($formatterName);
            $formatter->format($this, $document);
        }
    }

    public function setFormattersNames(array $formattersNames)
    {
        $this->formattersNames = $formattersNames;
    }

    public function addFormatterName($formatterName)
    {
        $this->formattersNames[] = $formatterName;
    }

    public function getFormattersNames()
    {
        return $this->formattersNames;
    }
    
    /**
     * @return \PHPPdf\Engine\GraphicsContext
     */
    public function getGraphicsContext()
    {
        return $this->getPage()->getGraphicsContext();
    }

    public function getPlaceholder($name)
    {
        return null;
    }

    public function hasPlaceholder($name)
    {
        return false;
    }

    /**
     * Set placeholder
     * 
     * @param string $name Name of placeholder
     * @param Node $placeholder Object of Node
     * 
     * @throws InvalidArgumentException Placeholder isn't supported by node
     */
    public function setPlaceholder($name, Node $placeholder)
    {
        throw new \InvalidArgumentException(sprintf('Placeholder "%s" is not supported by class "%s".', $name, get_class($this)));
    }

    protected function getDataForSerialize()
    {
        $data = array(
            'boundary' => $this->getBoundary(),
            'attributes' => $this->attributes,
            'enhancementBag' => $this->enhancementBag->getAll(),
            'formattersNames' => $this->formattersNames,
            'priority' => $this->priority,
        );

        return $data;
    }
    
    public function serialize()
    {
        $data = $this->getDataForSerialize();

        return serialize($data);
    }

    public function unserialize($serialized)
    {
        static::initializeTypeIfNecessary();

        $data = unserialize($serialized);

        $this->setDataFromUnserialize($data);
    }
    
    protected function setDataFromUnserialize(array $data)
    {       
        $this->setBoundary($data['boundary']);
        $this->attributes = $data['attributes'];
        $this->enhancementBag = new EnhancementBag($data['enhancementBag']);
        $this->setFormattersNames($data['formattersNames']);
        $this->priority = $data['priority'];
    }
    
    /**
     * Method from NodeAware interface
     * 
     * @return Node
     */
    public function getNode()
    {
        return $this;
    }

    public function __toString()
    {
        return get_class($this).\spl_object_hash($this);
    }
    
    public function getAncestorWithRotation()
    {
        if($this->ancestorWithRotation === null)
        {
            $parent = $this->getParent();
            $this->ancestorWithRotation = $this->getRotate() === null ? ($parent ? $parent->getAncestorWithRotation() : false) : $this;
        }

        return $this->ancestorWithRotation;
    }
    
    protected function getAncestorWithFontSize()
    {
        if($this->ancestorWithFontSize === null)
        {
            $parent = $this->getParent();
            $this->ancestorWithFontSize = $this->getFontSize() === null ? ($parent ? $parent->getAncestorWithFontSize() : false) : $this;
        }
        
        return $this->ancestorWithFontSize;
    }
}