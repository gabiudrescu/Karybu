<?php
/**
 * Models the &lt;column&gt; tag inside an XML Query file whose action is 'insert-select'
 *
 * @author Corina Udrescu (corina.udrescu@arnia.ro)
 * @package classes\xml\xmlquery\tags\column
 * @version 0.1
 */
class InsertColumnTagWithoutArgument extends ColumnTag
{
	/**
	 * Constructor
	 *
	 * @param object $column
	 * @return void
	 */
	function InsertColumnTagWithoutArgument($column)
	{
		parent::ColumnTag($column->attrs->name);
		$dbParser = DB::getDbParser();
		$this->name = $dbParser->parseColumnName($this->name);
	}

	/**
	 * Returns the string to be output in the cache file
	 *
	 * @return string
	 */
	function getExpressionString()
	{
		return sprintf('new Expression(\'%s\')', $this->name);
	}

	/**
	 * Returns the QueryArgument object associated with this INSERT statement
	 *
	 * @return null
	 */
	function getArgument()
	{
		return NULL;
	}

}
?>
