<?php

/**
 * Class Annotatable
 *
 * Annotate extension for the provided DataObjects for autocompletion purposes.
 * Start annotation, if skipannotation is not set and the annotator is enabled.
 *
 * @package IDEAnnotator/Extensions
 *
 * @property DataObject|Annotatable $owner
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
     * Keep track ot the annotation actions for extensions
     * An Extension can belong to many DataObjects.
     * This prevents that an Extension is ran twice on dev/build
     * @var array
     */
    public static $annotated_extensions = array();

    /**
     * Annotatable setup.
     * This is theoratically a constructor, but to save memory we're using setup called from {@see requireDefaultRecords}
     */
    public function setUp()
    {
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
        // Setup the protected values.
        $this->setUp();

        /** @var SS_HTTPRequest|NullHTTPRequest $request */
        $request = Controller::curr()->getRequest();
        $skipAnnotation = $request->getVar('skipannotation');
        if ($skipAnnotation !== null || !$this->permissionChecker->environmentIsAllowed()) {
            return false;
        }

        $this->generateClassAnnotations();
        $this->generateExtensionAnnotations();

        return true;
    }

    /**
     * Generate class own annotations
     */
    private function generateClassAnnotations()
    {
        /* Annotate the current Class, if annotatable */
        if ($this->permissionChecker->classNameIsAllowed($this->owner->ClassName)) {
            $this->annotator->annotateDataObject($this->owner->ClassName);
        }
    }

    /**
     * Generate class Extension annotations
     */
    private function generateExtensionAnnotations()
    {
        /** @var array $extensions */
        $extensions = Config::inst()->get($this->owner->ClassName, 'extensions', Config::UNINHERITED);
        /* Annotate the extensions for this Class, if annotatable */
        if (null !== $extensions) {
            foreach ($extensions as $extension) {
                if ($this->permissionChecker->classNameIsAllowed($extension)) {
                    // no need to run many times
                    if(!in_array($extension, Annotatable::$annotated_extensions)) {
                        $this->annotator->annotateDataObject($extension);
                        Annotatable::$annotated_extensions[$extension] = $extension;
                    }
                }
            }
        }
    }
}
