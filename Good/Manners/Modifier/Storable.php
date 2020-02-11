<?php

namespace Good\Manners\Modifier;

use Good\Rolemodel\Schema;

class Storable implements \Good\Service\Modifier
{
    private $className;
    private $classMembers;
    private $classVariable;
    private $classVariableIsReference;
    private $firstClass;
    private $accept;
    private $setFromArray;
    private $toArray;
    private $debugInfo;

    private $resolver = null;
    private $resolverVisit = null;
    private $extraFiles;

    public function __construct()
    {
    }

    public function baseClassTopOfFile()
    {
        return '';
    }

    public function implementingInterfaces()
    {
        return array('\\Good\\Manners\\GeneratedStorable');
    }

    public function baseClassConstructor()
    {
        return '';
    }
    public function baseClassBody()
    {
        $res  = "    // Storable\n";
        $res .= '    private $isNew = true;' . "\n";
        $res .= '    private $id = null;' . "\n";
        $res .= "    \n";
        $res .= '    public function isNew()'. "\n";
        $res .= "    {\n";
        $res .= '        return $this->isNew;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function setNew($value)'. "\n";
        $res .= "    {\n";
        $res .= '        $this->isNew = $value;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function getId()' . "\n";
        $res .= "    {\n";
        $res .= '        if ($this->id === null)' . "\n";
        $res .= "        {\n";
        $res .= '            throw new \Exception("Uninitilized id requested!");' . "\n";
        $res .= "        }\n";
        $res .= "        \n";
        $res .= '        return $this->id;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function setId($value)' . "\n";
        $res .= "    {\n";
        $res .= '        \\Good\\Service\\TypeChecker::checkString($value, 1);' . "\n";;
        $res .= '        $this->id = $value;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function hasValidId()' . "\n";
        $res .= "    {\n";
        $res .= '        return $this->id !== null;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";

        return $res;
    }

    public function visitSchema(Schema $schema)
    {
        $this->extraFiles = array();
        $this->firstClass = true;
    }
    public function visitSchemaEnd()
    {
        $this->finishDataType();
    }

    public function visitTypeDefinition(Schema\TypeDefinition $dataType)
    {
        if ($this->firstClass)
        {
            $this->firstClass = false;
        }
        else
        {
            $this->finishDataType();
        }

        $this->className = $dataType->getName();
        $this->classMembers = array();

        $this->accept  = '    public function acceptStorableVisitor(\\Good\\Manners\\StorableVisitor $visitor)' . "\n";
        $this->accept .= "    {\n";

        $this->setFromArray  = '    public function setFromArray(array $data)' . "\n";
        $this->setFromArray .= "    {\n";
        $this->setFromArray .= '        foreach ($data as $field => $value)' . "\n";
        $this->setFromArray .= "        {\n";
        $this->setFromArray .= '            switch ($field)' . "\n";
        $this->setFromArray .= "            {\n";

        $this->toArray  = '    public function toArray($datesToIso)' . "\n";
        $this->toArray .= "    {\n";
        $this->toArray .= "        return [\n";
        $this->toArray .= '            "id" => $this->id,' . "\n";

        $this->debugInfo  = '    public function __debugInfo()' . "\n";
        $this->debugInfo .= "    {\n";
        $this->debugInfo .= "        return [\n";
        $this->debugInfo .= '            "id" => $this->id,' . "\n";

        $this->resolver  = "<?php\n";
        $this->resolver .= "\n";
        $this->resolver .= 'class ' . $dataType->getName() . 'Resolver extends \\Good\\Manners\\BaseResolver' . "\n";
        $this->resolver .= "{\n";
        $this->resolver .= '    public function getType()' . "\n";
        $this->resolver .= "    {\n";
        $this->resolver .= '        return "' . $this->className . '";' . "\n";
        $this->resolver .= "    }\n";
        $this->resolver .= "    \n";

        $this->resolverVisit  = '    public function acceptResolverVisitor' .
                                                '(\\Good\\Manners\\ResolverVisitor $visitor)' . "\n";
        $this->resolverVisit .= "    {\n";
    }

    private function finishDataType()
    {
        $this->resolverVisit .= "    }\n";
        $this->resolverVisit .= "    \n";

        $this->resolver .= $this->resolverVisit;

        $this->resolver .= "}\n";
        $this->resolver .= "\n";
        $this->resolver .= "?>";

        $this->extraFiles[$this->className . 'Resolver.php'] = $this->resolver;
    }

    public function visitReferenceMember(Schema\Member $member, Schema\Type\ReferenceType $type)
    {
        $this->classVariable = $member->getName();
        $this->classMembers[] = $this->classVariable;

        $this->classVariableIsReference = true;

        $this->accept .= '        $visitor->visitReferenceProperty("' . $member->getName() . '", ' .
                                            '"' . $type->getReferencedType() . '", ' .
                                            '$this->is' . \ucfirst($member->getName()) . 'Dirty, ' .
                                            '$this->' . $member->getName() . ');' . "\n";

        $this->setFromArray .= '                case "' . $this->classVariable . '":' . "\n";
        $this->setFromArray .= '                    $this->set' . \ucfirst($this->classVariable) . '($value);'. "\n";
        $this->setFromArray .= '                    break;' . "\n";

        $this->toArray .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ' == null ?' . "\n";
        $this->toArray .= '                null : $this->' . $this->classVariable . '->toArray($datesToIso),' . "\n";

        $this->resolver .= '    private $resolved' . \ucfirst($member->getName()) . ' = null;' . "\n";
        $this->resolver .= "    \n";
        $this->resolver .= '    public function resolve' . \ucfirst($member->getName()) . '()' . "\n";
        $this->resolver .= "    {\n";
        $this->resolver .= '        $this->resolved' . \ucfirst($member->getName()) . ' = ' .
                                        'new ' . $type->getReferencedType() .
                                                                'Resolver($this->root);' . "\n";
        $this->resolver .= "        \n";
        $this->resolver .= '        return $this->resolved' . \ucfirst($member->getName()) . ';' . "\n";
        $this->resolver .= "    }\n";
        $this->resolver .= "    \n";
        $this->resolver .= '    public function get' . \ucfirst($member->getName()) . '()' . "\n";
        $this->resolver .= "    {\n";
        $this->resolver .= '        return $this->resolved' . \ucfirst($member->getName()) . ';' . "\n";
        $this->resolver .= "    }\n";
        $this->resolver .= "    \n";

        $this->resolverVisit .= '        if ($this->resolved' . \ucfirst($member->getName()) . ' != null)' . "\n";
        $this->resolverVisit .= "        {\n";
        $this->resolverVisit .= '            $visitor->resolverVisitResolvedReferenceProperty("' .
                                            $member->getName() . '", "' . $type->getReferencedType() .
                                            '", ' . '$this->resolved' . \ucfirst($member->getName()) .
                                            ');' . "\n";
        $this->resolverVisit .= "        }\n";
        $this->resolverVisit .= '        else' . "\n";
        $this->resolverVisit .= "        {\n";
        $this->resolverVisit .= '            $visitor->resolverVisitUnresolvedReferenceProperty(' .
                                            '"' . $member->getName() . '");' . "\n";
        $this->resolverVisit .= "        }\n";

        $this->debugInfo .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ',' . "\n";
        $this->debugInfo .= '            "is' . \ucfirst($this->classVariable) . 'Dirty" => $this->is' . \ucfirst($this->classVariable) . 'Dirty,' . "\n";
    }
    public function visitTextMember(Schema\Member $member, Schema\Type\TextType $type)
    {
        $this->classVariable = $member->getName();
        $this->classMembers[] = $this->classVariable;

        $this->classVariableIsReference = false;

        $this->accept .= '        $visitor->visitTextProperty("' . $member->getName() . '", ' .
                                            '$this->is' . \ucfirst($member->getName()) . 'Dirty, ' .
                                            '$this->' . $member->getName() . ');' . "\n";

        $this->setFromArray .= '                case "' . $this->classVariable . '":' . "\n";
        $this->setFromArray .= '                    $this->set' . \ucfirst($this->classVariable) . '(\strval($value));'. "\n";
        $this->setFromArray .= '                    break;' . "\n";

        $this->toArray .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ',' . "\n";

        $this->visitNonReference($member);
    }
    public function visitIntMember(Schema\Member $member, Schema\Type\IntType $type)
    {
        $this->classVariable = $member->getName();
        $this->classMembers[] = $this->classVariable;

        $this->classVariableIsReference = false;

        $this->accept .= '        $visitor->visitIntProperty("' . $member->getName() . '", ' .
                                            '$this->is' . \ucfirst($member->getName()) . 'Dirty, ' .
                                            '$this->' . $member->getName() . ');' . "\n";

        $this->setFromArray .= '                case "' . $this->classVariable . '":' . "\n";
        $this->setFromArray .= '                    $this->set' . \ucfirst($this->classVariable) . '(\intval($value));'. "\n";
        $this->setFromArray .= '                    break;' . "\n";

        $this->toArray .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ',' . "\n";

        $this->visitNonReference($member);
    }
    public function visitFloatMember(Schema\Member $member, Schema\Type\FloatType $type)
    {
        $this->classVariable = $member->getName();
        $this->classMembers[] = $this->classVariable;

        $this->classVariableIsReference = false;

        $this->accept .= '        $visitor->visitFloatProperty("' . $member->getName() . '", ' .
                                            '$this->is' . \ucfirst($member->getName()) . 'Dirty, ' .
                                            '$this->' . $member->getName() . ');' . "\n";

        $this->setFromArray .= '                case "' . $this->classVariable . '":' . "\n";
        $this->setFromArray .= '                    $this->set' . \ucfirst($this->classVariable) . '(\floatval($value));'. "\n";
        $this->setFromArray .= '                    break;' . "\n";

        $this->toArray .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ',' . "\n";

        $this->visitNonReference($member);
    }
    public function visitDatetimeMember(Schema\Member $member, Schema\Type\DateTimeType $type)
    {
        $this->classVariable = $member->getName();
        $this->classMembers[] = $this->classVariable;

        $this->classVariableIsReference = false;

        $this->accept .= '        $visitor->visitDatetimeProperty("' . $member->getName() . '", ' .
                                            '$this->is' . \ucfirst($member->getName()) . 'Dirty, ' .
                                            '$this->' . $member->getName() . ');' . "\n";

        $this->setFromArray .= '                case "' . $this->classVariable . '":' . "\n";
        $this->setFromArray .= '                    if ($value === null || $value instanceof \DateTimeImmutable)' . "\n";
        $this->setFromArray .= "                    {\n";
        $this->setFromArray .= '                        $this->set' . \ucfirst($this->classVariable) . '($value);'. "\n";
        $this->setFromArray .= "                    }\n";
        $this->setFromArray .= '                    else' . "\n";
        $this->setFromArray .= "                    {\n";
        $this->setFromArray .= '                        $this->set' . \ucfirst($this->classVariable) . '(new DateTimeImmutable($value, new DateTimeZone("UTC")));'. "\n";
        $this->setFromArray .= "                    }\n";
        $this->setFromArray .= '                    break;' . "\n";

        $this->toArray .= '            "' . $this->classVariable . '" => $datesToIso && $this->' . $this->classVariable . ' != null ?' . "\n";
        $this->toArray .= '                $this->' . $this->classVariable . '->format(\DateTimeImmutable::ATOM) : $this->' . $this->classVariable . ',' . "\n";

        $this->visitNonReference($member);
    }

    private function visitNonReference($member)
    {
        $this->resolver .= '    private $orderNumber' . \ucfirst($member->getName()) . ' = -1;' . "\n";
        $this->resolver .= '    private $orderDirection' . \ucfirst($member->getName()) . ' = -1;' . "\n";
        $this->resolver .= "    \n";
        $this->resolver .= '    public function orderBy' . \ucfirst($member->getName()) . 'Asc()' . "\n";
        $this->resolver .= "    {\n";
        $this->resolver .= '        $this->orderNumber' . \ucfirst($member->getName()) .
                                                        ' = $this->drawOrderTicket();' . "\n";
        $this->resolver .= '        $this->orderDirection' . \ucfirst($member->getName()) .
                                                        ' = self::ORDER_DIRECTION_ASC;' . "\n";
        $this->resolver .= '        return $this;' . "\n";
        $this->resolver .= "    }\n";
        $this->resolver .= "    \n";
        $this->resolver .= '    public function orderBy' . \ucfirst($member->getName()) . 'Desc()' . "\n";
        $this->resolver .= "    {\n";
        $this->resolver .= '        $this->orderNumber' . \ucfirst($member->getName()) .
                                                        ' = $this->drawOrderTicket();' . "\n";
        $this->resolver .= '        $this->orderDirection' . \ucfirst($member->getName()) .
                                                        ' = self::ORDER_DIRECTION_DESC;' . "\n";
        $this->resolver .= '        return $this;' . "\n";
        $this->resolver .= "    }\n";
        $this->resolver .= "    \n";

        $this->resolverVisit .= '        $visitor->resolverVisitNonReferenceProperty("' .
                                                            $member->getName() . '");' . "\n";
        $this->resolverVisit .= '        if ($this->orderNumber' . \ucfirst($member->getName()) . ' != -1)' . "\n";
        $this->resolverVisit .= "        {\n";
        $this->resolverVisit .= '            if ($this->orderDirection' . \ucfirst($member->getName()) .
                                                        '== self::ORDER_DIRECTION_ASC)' . "\n";
        $this->resolverVisit .= "            {\n";
        $this->resolverVisit .= '                $visitor->resolverVisitOrderAsc($this->orderNumber'
                                                    . \ucfirst($member->getName()) . ', "'
                                                    . $member->getName() . '");' . "\n";
        $this->resolverVisit .= "            }\n";
        $this->resolverVisit .= '            else' . "\n";
        $this->resolverVisit .= "            {\n";
        $this->resolverVisit .= '                $visitor->resolverVisitOrderDesc($this->orderNumber'
                                                    . \ucfirst($member->getName()) . ', "'
                                                    . $member->getName() . '");' . "\n";
        $this->resolverVisit .= "            }\n";
        $this->resolverVisit .= "        }\n";

        $this->debugInfo .= '            "' . $this->classVariable . '" => $this->' . $this->classVariable . ',' . "\n";
        $this->debugInfo .= '            "is' . \ucfirst($this->classVariable) . 'Dirty" => $this->is' . \ucfirst($this->classVariable) . 'Dirty,' . "\n";
    }

    public function varDefinitionBefore() {return '';}
    public function varDefinitionAfter()
    {
        // ucfirst: upper case first letter (it's a php built-in)
        $res  = '    private $is' . \ucfirst($this->classVariable) . 'Dirty =  false;' . "\n";
        $res .= "    \n";

        return $res;
    }
    public function getterBegin()
    {
        $res  = '        $this->GMMStorable_checkValidationToken();' . "\n";
        $res .= "        \n";

        return $res;
    }
    public function setterBegin()
    {
        $res  = '        $this->GMMStorable_checkValidationToken();' . "\n";
        $res .= "        \n";

        return $res;
    }
    public function setterEnd()
    {
        $res  = "        \n";
        // ucfirst: upper case first letter (it's a php built-in)
        $res .= '        $this->is' . \ucfirst($this->classVariable) . 'Dirty = true;' . "\n";
        $res .= '        $this->GMMStorable_makeDirty();' . "\n";

        return $res;
    }
    public function topOfGetterSwitch()
    {
        $res  = '            case "id":' . "\n";
        $res .= '                return $this->getid();' . "\n";
        $res .= "            \n";

        return $res;
    }

    public function topOfFile()
    {
        return '';
    }

    public function classBody()
    {
        $res  = '    private $dirty = false;' . "\n";
        $res .= "    \n";
        $res .= '    private function GMMStorable_makeDirty()' . "\n";
        $res .= "    {\n";
        $res .= '        if (!$this->isDirty() && $this->storage != null)' . "\n";
        $res .= "        {\n";
        $res .= '            $this->dirty = true;' . "\n";
        $res .= '            $this->storage->dirtyStorable($this);' . "\n";
        $res .= "        }\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function clean()' . "\n";
        $res .= "    {\n";
        $res .= '        $this->dirty = false;' . "\n";
        $res .= "        \n";
        foreach ($this->classMembers as $member)
        {
            $res .= '        $this->is' . ucfirst($member) . 'Dirty = false;' . "\n";
        }
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function isDirty()' . "\n";
        $res .= "    {\n";
        $res .= '        return $this->dirty;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    private $validationToken = null;' . "\n";
        $res .= "    \n";
        $res .= '    private function GMMStorable_checkValidationToken()' . "\n";
        $res .= "    {\n";
        $res .= '        if ($this->validationToken != null && !$this->validationToken->value())' . "\n";
        $res .= "        {\n";
                        // TODO: turn this into decent error handling
        $res .= '            throw new \\Exception("Tried to acces an invalid Storable. It was probably made invalid by actions" .' . "\n";
        $res .= '                 " on its storage (like doing a modify, which invalidates all its Storables).");' . "\n";
        $res .= "        }\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function setValidationToken(\\Good\\Manners\\ValidationToken $token)' . "\n";
        $res .= "    {\n";
        $res .= '        $this->validationToken = $token;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    private $storage = null;' . "\n";
        $res .= "    \n";
        $res .= '    public function setStorage(\\Good\\Manners\\Storage $storage)' . "\n";
        $res .= "    {\n";
        $res .= '        $this->storage = $storage;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    private $deleted = false;' . "\n";
        $res .= "    \n";
        $res .= '    public function isDeleted()'. "\n";
        $res .= "    {\n";
        $res .= '        return $this->deleted;' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function delete()'. "\n";
        $res .= "    {\n";
        $res .= '        $this->deleted = true;' . "\n";
        $res .= '        $this->GMMStorable_makeDirty();' . "\n";
        $res .= "    }\n";

        $res .= '    public static function resolver()' . "\n";
        $res .= "    {\n";
        $res .= '        return new ' . $this->className . 'Resolver();' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public static function id($storage, $id)' . "\n";
        $res .= "    {\n";
        $res .= '        return new \Good\Manners\Id(new ' . $this->className . '(), $storage, $id);' . "\n";
        $res .= "    }\n";
        $res .= "    \n";
        $res .= '    public function getType()' . "\n";
        $res .= "    {\n";
        $res .= '        return "' . $this->className . '";' . "\n";
        $res .= "    }\n";
        $res .= "    \n";

        $this->accept .= "    }\n";
        $this->accept .= "    \n";

        $res .= $this->accept;

        $this->setFromArray .= '                default:' . "\n";
        $this->setFromArray .= '                    throw new Exception("Unrecognised field in setFromArray array.");' . "\n";
        $this->setFromArray .= '                    break;' . "\n";
        $this->setFromArray .= "            }\n";
        $this->setFromArray .= "        }\n";
        $this->setFromArray .= "    }\n";

        $res .= $this->setFromArray;

        $this->toArray .= "        ];\n";
        $this->toArray .= "    }\n";
        $this->toArray .= "\n";

        $res .= $this->toArray;

        $this->debugInfo .= "        ];\n";
        $this->debugInfo .= "    }\n";
        $this->debugInfo .= "\n";

        $res .= $this->debugInfo;

        return $res;
    }
    public function bottomOfFile() {return '';}

    public function extraFiles()
    {
        return $this->extraFiles;
    }
}

?>
