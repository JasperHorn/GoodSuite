<?php

namespace Good\Rolemodel;

include_once 'TypePrimitive.php';

class TypePrimitiveText extends TypePrimitive
{
	public function accept(Visitor $visitor)
	{
		// visit this, there are no children to pass visitor on to
		$visitor->visitTypePrimitiveText($this);
	}
}

?>