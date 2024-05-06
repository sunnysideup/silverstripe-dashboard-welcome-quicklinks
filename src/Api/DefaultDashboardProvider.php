<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Upload;
use SilverStripe\CMS\Controllers\CMSSiteTreeFilter_ChangedPages;
use SilverStripe\CMS\Controllers\CMSSiteTreeFilter_StatusDraftPages;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\MFA\Report\EnabledMembers;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuicklinks;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

class DefaultDashboardProvider implements DashboardWelcomeQuickLinksProvider
{
    use Configurable;

    public function provideDashboardWelcomeQuickLinks(): array
    {
        $this->addPagesLinks();
        $this->addFindPages();
        $this->addFilesAndImages();
        $this->addSiteConfigLinks();
        if(Permission::check('ADMIN')) {
            $this->addSecurityLinks();
        }
        $this->addModelAdminLinks();
        $this->addMeLinks();
        return DashboardWelcomeQuicklinks::get_links();
    }


    private static $model_admins_to_skip = [
        ArchiveAdmin::class,
    ];
    private static $pages_to_skip = [

    ];


    protected function addPagesLinks()
    {
        DashboardWelcomeQuicklinks::add_group('PAGES', 'Pages', 10);
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('add'). ' Page', '/admin/pages/add');
        $pagesCount = DataObject::get('Page')->count();
        $draftCount = CMSSiteTreeFilter_StatusDraftPages::create()->getFilteredPages()->count();
        $revisedCount = CMSSiteTreeFilter_ChangedPages::create()->getFilteredPages()->count();
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' Pages ('.$pagesCount.')', '/admin/pages');
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' Unpublished Drafts ('.$draftCount.')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_StatusDraftPages');
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' Unpublished Changes ('.$revisedCount.')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_ChangedPages');
        $pageLastEdited = DataObject::get_one('Page', '', true, 'LastEdited DESC');
        if ($pageLastEdited) {
            DashboardWelcomeQuicklinks::add_link('PAGES', '✎ Last Edited Page: '.$pageLastEdited->Title, $pageLastEdited->CMSEditLink());
        }
        $lastWeekLink = '/admin/pages?'.'q[LastEditedFrom]='.date('Y-m-d', strtotime('-1 week'));
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Recently Modified Pages', $lastWeekLink);
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Page Reports', '/admin/reports');
    }

    protected function addFindPages()
    {
        $pages = [];
        $notUsedArray = [];
        $pagesToSkip = (array) $this->Config()->get('pages_to_skip');
        foreach (ClassInfo::subclassesFor(SiteTree::class, false) as $className) {
            if(in_array($className, $pagesToSkip)) {
                continue;
            }
            $pages[$className] = $className;

        }
        DashboardWelcomeQuicklinks::add_group('PAGEFILTER', 'Page Types ('.count($pages).')', 300);
        $count = 0;
        foreach($pages as $pageClassName) {
            $pageCount = $pageClassName::get()->filter(['ClassName' => $pageClassName])->count();
            if($pageCount < 1) {
                $notUsedArray[$pageClassName] = $pageClassName::singleton()->i18n_singular_name();
                continue;
            }
            $count++;
            if($pageCount === 1) {
                $obj = DataObject::get_one($pageClassName, ['ClassName' => $pageClassName]);
                DashboardWelcomeQuicklinks::add_link('PAGEFILTER', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' '.$pageClassName::singleton()->i18n_singular_name() . ' (1)', $obj->CMSEditLink());
                continue;
            }
            $page = Injector::inst()->get($pageClassName);
            $pageTitle = $page->i18n_plural_name();
            $query = 'q[ClassName]='.$pageClassName;
            $link = 'admin/pages?' . $query;
            DashboardWelcomeQuicklinks::add_link('PAGEFILTER', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' '.$pageTitle.' ('.$pageCount.')', $link);
        }
        foreach($notUsedArray as $pageClassName => $pageTitle) {
            DashboardWelcomeQuicklinks::add_link('PAGEFILTER', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' '.$pageTitle.' (0)', 'admin/pages/add?PageType='.$pageClassName);
        }
    }
    protected function addFilesAndImages()
    {
        // 'Files ('.$filesCount.') and Images ('.$imageCount.')'
        DashboardWelcomeQuicklinks::add_group('FILESANDIMAGES', 'Files and Images', 20);
        $uploadFolderName = Config::inst()->get(Upload::class, 'uploads_folder');
        $uploadFolder = Folder::find_or_make($uploadFolderName);
        // all
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' Open File Browswer', '/admin/assets');
        // per type
        $filesCount = File::get()->exclude(['ClassName' => [Folder::class, Image::class]])->count();
        $imageCount = File::get()->filter(['ClassName' => [Image::class]])->count();
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Images ('.$imageCount.')', 'admin/assets?filter[appCategory]=IMAGE');
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Files ('.$filesCount.')', 'admin/assets?filter[appCategory]=DOCUMENT');

        // default upload folder
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Open Default Upload Folder', $uploadFolder->CMSEditLink());

        // recent
        $lastWeekLink = '/admin/assets?'.'filter[lastEditedFrom]='.date('Y-m-d', strtotime('-1 week')).'&view=tile';
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Recently modified Files', $lastWeekLink);
    }

    protected function addSiteConfigLinks()
    {
        DashboardWelcomeQuicklinks::add_group('SITECONFIG', 'Site Wide Configuration', 20);
        DashboardWelcomeQuicklinks::add_link('SITECONFIG', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Site Settings', '/admin/settings');
    }

    protected function addSecurityLinks()
    {
        DashboardWelcomeQuicklinks::add_group('SECURITY', 'Security', 30);
        DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('add'). ' User', '/admin/security/users/EditForm/field/users/item/new');
        $userCount = Member::get()->count();
        $groupCount = Group::get()->count();
        DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Users ('.$userCount.')', '/admin/security');
        DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Groups  ('.$groupCount.')', '/admin/security/groups');
        DefaultAdminService::singleton()->extend('addSecurityLinks', $this);
        $adminGroup = Permission::get_groups_by_permission('ADMIN')->first();
        if($adminGroup) {
            $userCount = $adminGroup->Members()->count();
            DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Administrators ('.$userCount.')', '/admin/security/groups/EditForm/field/groups/item/'.$adminGroup->ID.'/edit');
        }
        if(class_exists(EnabledMembers::class)) {
            $obj = Injector::inst()->get(EnabledMembers::class);
            DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review'). ' Multi-Factor Authentication Status', $obj->getLink());

        }
    }




    protected function addModelAdminLinks()
    {
        $modelAdmins = [];
        $skips = (array) $this->Config()->get('model_admins_to_skip');
        foreach (ClassInfo::subclassesFor(ModelAdmin::class, false) as $className) {
            if(in_array($className, $skips)) {
                continue;
            }
            $modelAdmins[$className] = $className;

        }
        foreach($modelAdmins as $modelAdminClassName) {
            $groupAdded = false;
            $ma = Injector::inst()->get($modelAdminClassName);
            if($ma->canView()) {
                $mas = $ma->getManagedModels();
                if(count($mas)) {
                    $numberOfModels = count($mas);
                    $groupCode = strtoupper($modelAdminClassName);
                    $count = 0;
                    foreach($mas as $model => $title) {
                        $count++;
                        if(is_array($title)) {
                            $title = $title['title'];
                            $model = $title['dataClass'] ?? $model;
                        }
                        if(! class_exists($model)) {
                            continue;
                        }
                        $obj = Injector::inst()->get($model);
                        if($obj && $obj->canView()) {
                            if(! $groupAdded) {
                                DashboardWelcomeQuicklinks::add_group($groupCode, $ma->menu_title(), 100);
                                $groupAdded = true;
                            }
                            // $classNameList = ClassInfo::subclassesFor($model);
                            $ma = ReflectionHelper::allowAccessToProperty(get_class($ma), 'modelClass');
                            $ma->modelClass = $model;
                            $list = $ma->getList();
                            if(! $list) {
                                $list = $model::get();
                            }
                            $objectCount = $list->count();
                            if($objectCount === 1) {
                                $obj = DataObject::get_one($model, ['ClassName' => $model]);
                                if(! $obj) {
                                    $obj = DataObject::get_one($model);
                                }
                                if($obj && $obj->hasMethod('CMSEditLink')) {
                                    DashboardWelcomeQuicklinks::add_link($groupCode, DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' '.$model::singleton()->i18n_singular_name(), $obj->CMSEditLink());
                                    continue;
                                }
                            }

                            $link = '';
                            if($obj->hasMethod('CMSListLink')) {
                                $link = $obj->CMSListLink();
                            } if(! $link) {
                                $link = $ma->getLinkForModelTab($model);
                            }
                            $titleContainsObjectCount = strpos($title, ' ('.$objectCount.')');
                            if($titleContainsObjectCount === false) {
                                $title .= ' ('.$objectCount.')';
                            }
                            DashboardWelcomeQuicklinks::add_link($groupCode, DashboardWelcomeQuicklinks::get_base_phrase('edit'). ' '.$title, $link);
                            if($numberOfModels < 4) {
                                $obj = Injector::inst()->get($model);
                                if($obj->canCreate()) {
                                    $classNameEscaped = str_replace('\\', '-', $model);
                                    $linkNew = $link .= '/EditForm/field/'.$classNameEscaped.'/item/new';
                                    DashboardWelcomeQuicklinks::add_link($groupCode, DashboardWelcomeQuicklinks::get_base_phrase('add'). ' '.$obj->i18n_singular_name(), $linkNew);
                                }
                            }
                        }
                    }
                }
            }
        }
    }



    protected function addMeLinks()
    {
        DashboardWelcomeQuicklinks::add_group('ME', 'My Account', 200);
        DashboardWelcomeQuicklinks::add_link('ME', DashboardWelcomeQuicklinks::get_base_phrase('edit') . '  My Details', '/admin/myprofile');
        DashboardWelcomeQuicklinks::add_link('ME', DashboardWelcomeQuicklinks::get_base_phrase('review') . '  Test Password Reset', 'Security/lostpassword');
        DashboardWelcomeQuicklinks::add_link('ME', DashboardWelcomeQuicklinks::get_base_phrase('review') . '  Log-out', '/Security/logout');
    }




}
