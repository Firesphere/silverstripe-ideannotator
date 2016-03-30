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
     * Annotatable constructor.
     * I'm unsure if setting these on construct is a good idea. It might cause higher memory usage.
     */
    public function __construct()
    {
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

        $this->generateClassAnnotations();
        $this->generateExtensionAnnotations();

        return null;
    }

    /**
     * Generate class own annotations
     */
    private function generateClassAnnotations()
    {
        /* Annotate the current Class, if annotatable */
        if ($this->permissionChecker->classNameIsAllowed($this->owner->ClassName) === true) {
            if ($this->annotator->annotateDataObject($this->owner->ClassName) === true) {
                DB::alteration_message($this->owner->ClassName . ' Annotated', 'created');
            }
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
                    if ($this->annotator->annotateDataObject($extension) === true) {
                        DB::alteration_message($extension . ' Annotated', 'created');
                    }
                }
            }
        }
    }
}
