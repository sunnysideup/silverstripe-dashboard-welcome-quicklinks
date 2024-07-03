<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
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
use SilverStripe\Security\PermissionRole;
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
        if (Permission::check('ADMIN')) {
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
        $pagesCount = DataObject::get('Page')->count();
        $draftCount = CMSSiteTreeFilter_StatusDraftPages::create()->getFilteredPages()->count();
        $revisedCount = CMSSiteTreeFilter_ChangedPages::create()->getFilteredPages()->count();
        $add = [
            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
            'Link' => '/admin/pages/add',
        ];
        DashboardWelcomeQuicklinks::add_link(
            'PAGES',
            DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Pages (' . $pagesCount . ')',
            '/admin/pages',
            $add
        );
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Unpublished Drafts (' . $draftCount . ')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_StatusDraftPages');
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Unpublished Changes (' . $revisedCount . ')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_ChangedPages');
        $pageLastEdited = DataObject::get_one('Page', '', true, 'LastEdited DESC');
        if ($pageLastEdited) {
            DashboardWelcomeQuicklinks::add_link('PAGES', '✎ Last Edited Page: ' . $pageLastEdited->Title, $pageLastEdited->CMSEditLink());
        }
        $lastWeekLink = '/admin/pages?q[LastEditedFrom]=' . date('Y-m-d', strtotime('-1 week'));
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Recently Modified Pages', $lastWeekLink);
        DashboardWelcomeQuicklinks::add_link('PAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Page Reports', '/admin/reports');
    }

    protected function addFindPages()
    {
        $pages = [];
        $pagesToSkip = (array) $this->Config()->get('pages_to_skip');
        foreach (ClassInfo::subclassesFor(SiteTree::class, false) as $className) {
            if (in_array($className, $pagesToSkip)) {
                continue;
            }
            $pages[$className] = $className;
        }
        DashboardWelcomeQuicklinks::add_group('PAGEFILTER', 'Page Types (' . count($pages) . ')', 300);
        $pagesArray = [];
        foreach ($pages as $pageClassName) {
            $pageCount = $pageClassName::get()->filter(['ClassName' => $pageClassName])->count();
            if ($pageCount < 1) {
                $page = Injector::inst()->get($pageClassName);
                if ($page->canCreate()) {
                    $pageTitle = $page->i18n_singular_name();
                    $pagesArray[] = [
                        'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add') . ' ' . $pageTitle . ' (0)',
                        'Link' => 'admin/pages/add?PageType=' . $pageClassName,
                        'Count' => 0,
                        'InsideLink' => [],
                    ];
                }
            } elseif ($pageCount === 1) {
                $page = DataObject::get_one($pageClassName, ['ClassName' => $pageClassName]);
                if ($page->canEdit()) {
                    $pageTitle = $page->i18n_singular_name();
                    $insideLink = [];
                    if ($page->canCreate() && $page->config()->get('can_be_root')) {
                        $insideLink = [
                            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
                            'Link' => 'admin/pages/add?PageType=' . $pageClassName,
                        ];
                    }
                    $pagesArray[] = [
                        'Title' => DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' ' . $pageTitle . ' (1)',
                        'Link' => $page->CMSEditLink(),
                        'Count' => 1,
                        'InsideLink' => $insideLink,
                    ];
                }
            } else {
                $page = Injector::inst()->get($pageClassName);
                if ($page->canEdit()) {
                    $pageTitle = $page->i18n_plural_name();
                    $query = 'q[ClassName]=' . $pageClassName;
                    $link = 'admin/pages?' . $query;
                    $insideLink = [];
                    if ($page->canCreate() && $page->config()->get('can_be_root')) {
                        $insideLink = [
                            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
                            'Link' => 'admin/pages/add?PageType=' . $pageClassName,
                        ];
                    }
                    $pagesArray[] = [
                        'Title' => DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' ' . $pageTitle . ' (' . $pageCount . ')',
                        'Link' => $link,
                        'Count' => $pageCount,
                        'InsideLink' => $insideLink,
                    ];
                }
            }
        }
        $pagesArray = $this->sortByCountAndTitle($pagesArray);
        foreach ($pagesArray as $pageArray) {
            DashboardWelcomeQuicklinks::add_link('PAGEFILTER', $pageArray['Title'], $pageArray['Link'], $pageArray['InsideLink']);
        }
    }

    protected function addFilesAndImages()
    {
        // 'Files ('.$filesCount.') and Images ('.$imageCount.')'
        DashboardWelcomeQuicklinks::add_group('FILESANDIMAGES', 'Files and Images', 20);
        $uploadFolderName = Config::inst()->get(Upload::class, 'uploads_folder');
        $uploadFolder = Folder::find_or_make($uploadFolderName);
        // all
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Open File Browswer', '/admin/assets');
        // per type
        $filesCount = File::get()->excludeAny(['ClassName' => [Folder::class, Image::class]])->count();
        $imageCount = File::get()->filter(['ClassName' => Image::class])->exclude(['ClassName' => Folder::class])->count();
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Files (' . $filesCount . ')', 'admin/assets?filter[appCategory]=DOCUMENT');
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Images (' . $imageCount . ')', 'admin/assets?filter[appCategory]=IMAGE');

        // default upload folder
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Open Default Upload Folder', $uploadFolder->CMSEditLink());

        // recent
        $lastWeekLink = '/admin/assets?filter[lastEditedFrom]=' . date('Y-m-d', strtotime('-1 week')) . '&view=tile';
        DashboardWelcomeQuicklinks::add_link('FILESANDIMAGES', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Recently modified Files', $lastWeekLink);
    }

    protected function addSiteConfigLinks()
    {
        DashboardWelcomeQuicklinks::add_group('SITECONFIG', 'Site Wide Configuration', 20);
        DashboardWelcomeQuicklinks::add_link('SITECONFIG', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Site Settings', '/admin/settings');
    }

    protected function addSecurityLinks()
    {
        DashboardWelcomeQuicklinks::add_group('SECURITY', 'Security', 30);
        $userCount = Member::get()->count();
        $add = [
            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
            'Link' => '/admin/security/users/EditForm/field/users/item/new',
        ];
        DashboardWelcomeQuicklinks::add_link(
            'SECURITY',
            DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Users (' . $userCount . ')',
            '/admin/security',
            $add
        );
        // groups
        $groupCount = Group::get()->count();
        $add = [
            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
            'Link' => '/admin/security/groups/EditForm/field/groups/item/new',
        ];
        DashboardWelcomeQuicklinks::add_link(
            'SECURITY',
            DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Groups  (' . $groupCount . ')',
            '/admin/security/groups',
            $add
        );
        DefaultAdminService::singleton()->extend('addSecurityLinks', $this);
        $adminGroup = Permission::get_groups_by_permission('ADMIN')->first();
        if ($adminGroup) {
            $userCount = $adminGroup->Members()->count();
            DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Administrators (' . $userCount . ')', '/admin/security/groups/EditForm/field/groups/item/' . $adminGroup->ID . '/edit');
        }

        // roles
        $roleCount = PermissionRole::get()->count();
        $add = [
            'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
            'Link' => '/admin/security/roles/EditForm/field/roles/item/new',
        ];
        DashboardWelcomeQuicklinks::add_link(
            'SECURITY',
            DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' Permission Roles  (' . $roleCount . ')',
            '/admin/security/groups',
            $add
        );
        // multi factor
        if (class_exists(EnabledMembers::class)) {
            $obj = Injector::inst()->get(EnabledMembers::class);
            DashboardWelcomeQuicklinks::add_link('SECURITY', DashboardWelcomeQuicklinks::get_base_phrase('review') . ' Multi-Factor Authentication Status', $obj->getLink());
        }
    }

    protected function addModelAdminLinks()
    {
        $modelAdmins = [];
        $skips = (array) $this->Config()->get('model_admins_to_skip');
        foreach (ClassInfo::subclassesFor(ModelAdmin::class, false) as $className) {
            if (in_array($className, $skips)) {
                continue;
            }
            $modelAdmins[$className] = $className;
        }
        foreach ($modelAdmins as $modelAdminClassName) {
            $groupAdded = false;
            $ma = Injector::inst()->get($modelAdminClassName);
            if ($ma->canView()) {
                $mas = $ma->getManagedModels();
                if (count($mas) > 0) {
                    $numberOfModels = count($mas);
                    $groupCode = strtoupper($modelAdminClassName);
                    $count = 0;
                    foreach ($mas as $model => $title) {
                        $count++;
                        if (is_array($title)) {
                            $title = $title['title'];
                            $model = $title['dataClass'] ?? $model;
                        }
                        if (! class_exists($model)) {
                            continue;
                        }
                        $obj = Injector::inst()->get($model);
                        if ($obj && $obj->canView()) {
                            if (! $groupAdded) {
                                DashboardWelcomeQuicklinks::add_group($groupCode, $ma->menu_title(), 100);
                                $groupAdded = true;
                            }
                            // $classNameList = ClassInfo::subclassesFor($model);
                            $ma = ReflectionHelper::allowAccessToProperty(get_class($ma), 'modelClass');
                            $ma->modelClass = $model;
                            $list = $ma->getList();
                            if (! $list) {
                                $list = $model::get();
                            }
                            $objectCount = $list->count();
                            if ($objectCount === 1) {
                                $baseTable = Injector::inst()->get($model)->baseTable();
                                $obj = DataObject::get_one($model, [$baseTable . '.ClassName' => $model]);
                                if (! $obj) {
                                    $obj = DataObject::get_one($model);
                                }
                                if ($obj && $obj->hasMethod('CMSEditLink')) {
                                    $link = $obj->CMSEditLink();
                                    if ($link) {
                                        DashboardWelcomeQuicklinks::add_link($groupCode, DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' ' . $model::singleton()->i18n_singular_name(), $link);
                                        continue;
                                    }
                                }
                            }

                            $link = '';
                            if ($obj->hasMethod('CMSListLink')) {
                                $link = $obj->CMSListLink();
                            } if (! $link) {
                                $link = $ma->getLinkForModelTab($model);
                            }
                            $titleContainsObjectCount = strpos($title, ' (' . $objectCount . ')');
                            if ($titleContainsObjectCount === false) {
                                $title .= ' (' . $objectCount . ')';
                            }
                            $add = [];
                            if ($obj->canCreate()) {
                                $classNameEscaped = str_replace('\\', '-', $model);
                                $linkNew = $link . '/EditForm/field/' . $classNameEscaped . '/item/new';
                                $add = [
                                    'Title' => DashboardWelcomeQuicklinks::get_base_phrase('add'),
                                    'Link' => $linkNew,
                                ];
                            }
                            DashboardWelcomeQuicklinks::add_link(
                                $groupCode,
                                DashboardWelcomeQuicklinks::get_base_phrase('edit') . ' ' . $title,
                                $link,
                                $add
                            );
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

    protected function sortByCountAndTitle(array $array): array
    {
        usort($array, function ($a, $b) {
            $countComparison = $b['Count'] <=> $a['Count'];
            if ($countComparison === 0) {
                return $a['Title'] <=> $b['Title'];
            }
            return $countComparison;
        });

        return $array;
    }
}
