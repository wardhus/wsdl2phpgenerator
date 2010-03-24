<?php

/**
 * @package Wsdl2PhpGenerator
 */

/**
 * @see phpSourcePhpClass
 */
require_once dirname(__FILE__).'/../lib/phpSource/PhpClass.php';

/**
 * @see phpSourcePhpDocElementFactory.php
 */
require_once dirname(__FILE__).'/../lib/phpSource/PhpDocElementFactory.php';

/**
 * @see Operation
 */
require_once dirname(__FILE__).'/Operation.php';

/**
 * Service represents the service in the wsdl
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik@wallgren.me>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class wsdl2phpService
{
  /**
   *
   * @var phpSourcePhpClass The class used to create the service.
   */
  private $class;

  /**
   *
   * @var string The name of the service
   */
  private $identifier;

  /**
   *
   * @var array An array containing the operations of the service
   */
  private $operations;

  /**
   *
   * @var string The description of the service used as description in the phpdoc of the class
   */
  private $description;

  /**
   *
   * @var array An array of wsdl2phpTypes
   */
  private $types;

  /**
   *
   * @param string $identifier The name of the service
   * @param array $types The types the service knows about
   * @param string $description The description of the service
   */
  function __construct($identifier, array $types, $description)
  {
    $this->identifier = $identifier;
    $this->types = $types;
    $this->description = $description;
  }

  /**
   *
   * @return phpSourcePhpClass Returns the class, generates it if not done
   */
  public function getClass()
  {
    if($this->class == null)
    {
      $this->generateClass();
    }

    return $this->class;
  }

  /**
   * Generates the class if not already generated
   */
  public function generateClass()
  {
    $config = wsdl2phpGenerator::getInstance()->getConfig();

    // Add prefix and suffix
    $name = $config->getPrefix().$this->identifier.$config->getSuffix();

    // Generate a valid classname
    try
    {
      $name = wsdl2phpValidator::validateClass($name);
    }
    catch (wsdl2phpValidationException $e)
    {
      $name .= 'Custom';
    }

    // Create the class object
    $comment = new phpSourcePhpDocComment($this->description);
    $this->class = new phpSourcePhpClass($name, $config->getClassExists(), 'SoapClient', $comment);

    // Create the constructor
    $comment = new phpSourcePhpDocComment();
    $comment->addParam(phpSourcePhpDocElementFactory::getParam('array', 'config', 'A array of config values'));
    $comment->addParam(phpSourcePhpDocElementFactory::getParam('string', 'wsdl', 'The wsdl file to use'));
    $comment->setAccess(phpSourcePhpDocElementFactory::getPublicAccess());

    $source = '  foreach(self::$classmap as $key => $value)
  {
    if(!isset($options[\'classmap\'][$key]))
    {
      $options[\'classmap\'][$key] = $value;
    }
  }
  '.$this->generateServiceOptions($config).'
  parent::__construct($wsdl, $options);'.PHP_EOL;

    $function = new phpSourcePhpFunction('public', '__construct', 'array $options = array(), $wsdl = \''.$config->getInputFile().'\'', $source, $comment);

    // Add the constructor
    $this->class->addFunction($function);

    // Generate the classmap
    $name = 'classmap';
    $comment = new phpSourcePhpDocComment();
    $comment->setAccess(phpSourcePhpDocElementFactory::getPrivateAccess());
    $comment->setVar(phpSourcePhpDocElementFactory::getVar('array', $name, 'The defined classes'));

    $init = 'array('.PHP_EOL;
    foreach ($this->types as $type)
    {
      if($type instanceof wsdl2phpComplexType)
      {
        $init .= "  '".$type->getIdentifier()."' => '".$type->getPhpIdentifier()."',".PHP_EOL;
      }
    }
    $init = substr($init, 0, strrpos($init, ','));
    $init .= ')';
    $var = new phpSourcePhpVariable('private static', $name, $init, $comment);

    // Add the classmap variable
    $this->class->addVariable($var);

    // Add all methods
    foreach ($this->operations as $operation)
    {
      $name = wsdl2phpValidator::validateNamingConvention($operation->getName());

      $comment = new phpSourcePhpDocComment($operation->getDescription());
      $comment->setAccess(phpSourcePhpDocElementFactory::getPublicAccess());
      
      foreach ($operation->getParams() as $param => $hint)
      {
        $arr = $operation->getPhpDocParams($param, $this->types);
        $comment->addParam(phpSourcePhpDocElementFactory::getParam($arr['type'], $arr['name'], $arr['desc']));
      }

      $source = '  return $this->__soapCall(\''.$name.'\', array('.$operation->getParamStringNoTypeHints().'));'.PHP_EOL;

      $paramStr = $operation->getParamString($this->types);

      $function = new phpSourcePhpFunction('public', $name, $paramStr, $source, $comment);

      if ($this->class->functionExists($function->getIdentifier()) == false)
      {
        $this->class->addFunction($function);
      }
    }
  }

  /**
   * Adds an operation to the service
   *
   * @param string $name
   * @param array $params
   * @param string $description
   */
  public function addOperation($name, $params, $description)
  {
    $this->operations[] = new wsdl2phpOperation($name, $params, $description);
  }

  /**
   *
   * @param wsdl2phpConfig $config The config containing the values to use
   *
   * @return string Returns the string for the options array
   */
  private function generateServiceOptions(wsdl2phpConfig $config)
  {
    $ret = '';

    if (count($config->getOptionFeatures()) > 0)
    {
      $i = 0;
      $ret .= "
  if (isset(\$options['features']) == false)
  {
    \$options['features'] = ";
      foreach ($config->getOptionFeatures() as $option)
      {
        if ($i++ > 0)
        {
          $ret .= ' | ';
        }

        $ret .= $option;
      }

      $ret .= ";
  }".PHP_EOL;
    }

    if (strlen($config->getWsdlCache()) > 0)
    {
      $ret .= "
  if (isset(\$options['wsdl_cache']) == false)
  {
    \$options['wsdl_cache'] = ".$config->getWsdlCache();
      $ret .= ";
  }".PHP_EOL;
    }

    if (strlen($config->getCompression()) > 0)
    {
      $ret .= "
  if (isset(\$options['compression']) == false)
  {
    \$options['compression'] = ".$config->getCompression();
      $ret .= ";
  }".PHP_EOL;
    }

    return $ret;
  }
}