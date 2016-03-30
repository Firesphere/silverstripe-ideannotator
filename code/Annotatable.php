<?php

/**
 * Class Annotatable
 *
 * Annotate the provided DataObjects for autocompletion purposes.
 * Start annotation, if skipannotation is not set and the annotator is enabled.
 *
 * @property DataObject|Annotatable owner
 */
class Annotatable extends DataExtension implements Flushable
{

    /**
     * @var DataObjectAnnotator
     */
    protected $annotator;

    /**
     * @var AnnotatePermissionChecker
     */
    protected $permissionChecker;

    /**
     * Annotatable constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->annotator = Injector::inst()->get('DataObjectAnnotator');
        $this->permissionChecker = Injector::inst()->get('AnnotatePermissionChecker');
    }

    /**
     * After the request, generate the docs.
     * @inheritdoc
     */
    public static function flush()
    {
        if(Config::inst()->get('DataObjectAnnotator', 'generate_documentation') === true) {
            $modules = Config::inst()->get('DataObjectAnnotator', 'documentation_modules');
            foreach($modules as $module => $location) {
                exec(Director::baseFolder() . "/vendor/apigen/apigen/bin/apigen generate -q -s " . Director::baseFolder() . "/$module -d " . Director::baseFolder() . "/$location/$module --exclude=*tests* --todo ");
            }
        }
    }

    /**
     * This is the base function on which annotations are started.
     *
     * @todo rewrite this. It's not actually a requireDefaultRecords. But it's the only place to hook into the build-process to start the annotation process.
     * @return bool
     */
    public function requireDefaultRecords()
    {

        /** @var SS_HTTPRequest|NullHTTPRequest $request */
        $request = Controller::curr()->getRequest();
        $skipAnnotation = $request->getVar('skipannotation');
        if ($skipAnnotation !== null || !Config::inst()->get('DataObjectAnnotator', 'enabled')) {
            return false;
        }

        /* Annotate the current Class, if annotatable */
        if ($this->permissionChecker->classNameIsAllowed($this->owner->ClassName)) {
            $this->annotator->annotateDataObject($this->owner->ClassName);
        }

        /** @var array $extensions */
        $extensions = Config::inst()->get($this->owner->ClassName, 'extensions', Config::UNINHERITED);
        /* Annotate the extensions for this Class, if annotatable */
        if ($extensions) {
            foreach ($extensions as $extension) {
                if ($this->permissionChecker->classNameIsAllowed($extension)) {
                    $this->annotator->annotateDataObject($extension);
                }
            }
        }

        return null;
    }
}
