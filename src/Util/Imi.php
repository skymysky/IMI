<?php
namespace Imi\Util;

use Imi\App;
use Imi\Main\Helper;
use Imi\Bean\BeanProxy;
use Imi\Bean\Parser\BeanParser;

/**
 * 框架里杂七杂八的各种工具方法
 */
abstract class Imi
{
	/**
	 * 处理规则，暂只支持通配符*
	 * @param string $rule
	 * @return string
	 */
	public static function parseRule($rule)
	{
		return \str_replace('\\*', '.*', \preg_quote($rule));
	}

	/**
	 * 检查规则是否匹配，支持通配符*
	 * @param string $rule
	 * @param string $string
	 * @return boolean
	 */
	public static function checkRuleMatch($rule, $string)
	{
		$rule = '/^' . static::parseRule($rule) . '$/';
		return \preg_match($rule, $string) > 0;
	}

	/**
	 * 检查类和方法是否匹配，支持通配符*
	 * @param string $rule
	 * @param string $className
	 * @param string $methodName
	 * @return boolean
	 */
	public static function checkClassMethodRule($rule, $className, $methodName)
	{
		list($classRule, $methodRule) = explode('::', $rule, 2);
		return static::checkRuleMatch($classRule, $className) && static::checkRuleMatch($methodRule, $methodName);
	}

	/**
	 * 检查类是否匹配，支持通配符*
	 * @param string $rule
	 * @param string $className
	 * @return boolean
	 */
	public static function checkClassRule($rule, $className)
	{
		list($classRule, ) = explode('::', $rule, 2);
		return static::checkRuleMatch($classRule, $className);
	}

	/**
	 * 检查验证比较规则集
	 * @param string|array $rules
	 * @param callable $valueCallback
	 * @return boolean
	 */
	public static function checkCompareRules($rules, $valueCallback)
	{
		foreach(is_array($rules) ? $rules : [$rules] as $fieldName => $rule)
		{
			if(is_numeric($fieldName))
			{
				if(!static::checkCompareRule($rule, $valueCallback))
				{
					return false;
				}
			}
			else if(preg_match('/^' . $rule . '$/', call_user_func($valueCallback, $fieldName)) <= 0)
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * 检查验证比较规则，如果符合规则返回bool，不符合规则返回null
	 * id=1
	 * id!=1 id<>1
	 * id
	 * !id
	 * @param string $rule
	 * @param callable $valueCallback
	 * @return boolean
	 */
	public static function checkCompareRule($rule, $valueCallback)
	{
		if(isset($rule[0]) && '!' === $rule[0])
		{
			// 不应该存在参数支持
			return null === call_user_func($valueCallback, substr($rule, 1));
		}
		else if(preg_match('/([^!<=]+)(!=|<>|=)(.+)/', $rule, $matches) > 0)
		{
			$value = call_user_func($valueCallback, $matches[1]);
			switch($matches[2])
			{
				case '!=':
				case '<>':
					return null !== $value && $value != $matches[3];
				case '=':
					return $value == $matches[3];
				default:
					return false;
			}
		}
		else
		{
			return null !== call_user_func($valueCallback, $rule);
		}
	}

	/**
	 * 检查验证比较值集
	 * @param string|array $rules
	 * @param mixed $value
	 * @return boolean
	 */
	public static function checkCompareValues($rules, $value)
	{
		foreach(is_array($rules) ? $rules : [$rules] as $rule)
		{
			if(!static::checkCompareValue($rule, $value))
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * 检查验证比较值
	 * @param string|array $rule
	 * @param mixed $value
	 * @return boolean
	 */
	public static function checkCompareValue($rule, $value)
	{
		if(isset($rule[0]) && '!' === $rule[0])
		{
			// 不等
			return $value !== $rule;
		}
		else
		{
			// 相等
			return $value === $rule;
		}
	}

	/**
	 * 处理按.分隔的规则文本，支持\.转义不分隔
	 * @param string $rule
	 */
	public static function parseDotRule($rule)
	{
		$result = preg_split('#(?<!\\\)\.#', $rule);
		array_walk($result, function(&$value, $key){
			if(false !== strpos($value,'\.'))
			{
				$value = str_replace('\.', '.', $value);
			}
		});
		return $result;
	}

	/**
	 * 获取类短名称
	 * @param string $className
	 * @return string
	 */
	public static function getClassShortName(string $className)
	{
		return implode('', array_slice(explode('\\', $className), -1));
	}

	/**
	 * 根据命名空间获取真实路径
	 * @param string $namespace
	 * @return string
	 */
	public static function getNamespacePath($namespace)
	{
		$appNamespace = App::getNamespace();
		$appMain = Helper::getMain($appNamespace);
		$refClass = new \ReflectionClass($appMain);
		$path = dirname($refClass->getFileName());
		$namespaceSubPath = substr($namespace, strlen($appNamespace));
		return File::path($path, str_replace('\\', DIRECTORY_SEPARATOR, $namespaceSubPath));
	}

	/**
	 * 获取类属性的值，支持传入Bean名称
	 *
	 * @param string $className
	 * @param string $propertyName
	 * @return mixed
	 */
	public static function getClassPropertyValue($className, $propertyName)
	{
		$value = BeanProxy::getInjectValue($className, $propertyName);
		if(null === $value)
		{
			if(!class_exists($className))
			{
				$className = BeanParser::getInstance()->getData()[$className]['className'];
			}
			$ref = new \ReflectionClass($className);
			$value = $ref->getDefaultProperties()[$propertyName] ?? null;
		}
		return $value;
	}
}