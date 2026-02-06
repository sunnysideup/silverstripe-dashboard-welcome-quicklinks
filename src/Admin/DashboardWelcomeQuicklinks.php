<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Sunnysideup\DashboardWelcomeQuicklinks\Api\DefaultDashboardProvider;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuicklinksProvider;

/**
 * Class \Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuicklinks
 *
 */
class DashboardWelcomeQuicklinks extends LeftAndMain
{
    protected static int $item_counter = 0;

    protected static int $group_counter = 0;

    protected static array $links = [];


    public static function add_group(string $groupCode, string $title, ?int $sort = 0)
    {
        self::$group_counter++;

        self::$links[$groupCode] = [
            'Title' => $title,
            'SortOrder' => $sort ?: self::$group_counter,
        ];
    }

    public static function add_link(string $groupCode, string $title, string $link, ?array $insideLink = [], ?string $tooltip = null)
    {
        self::$item_counter++;
        if (array_key_exists(0, $insideLink) && array_key_exists(1, $insideLink)) {
            $keys = ['Title', 'Link'];
            $insideLink = array_combine($keys, $insideLink);
        }
        self::$links[$groupCode]['Items'][] = [
            'Title' => $title,
            'Link' => $link,
            'InsideLink' => $insideLink,
            'Tooltip' => $tooltip
        ];
    }

    public static function get_links()
    {
        return self::$links;
    }

    public static function get_base_phrase(string $phrase): string
    {
        if (! in_array($phrase, ['add', 'review', 'edit', 'more'])) {
            user_error('Phrase must be one of "add", "review", or "edit"', E_USER_ERROR);
        }
        $phrase = Config::inst()->get(static::class, $phrase . '_phrase');
        return _t('DashboardWelcomeQuicklinks.' . $phrase, $phrase);
    }

    private static string $add_phrase = '+';

    private static string $review_phrase = '☑';

    private static string $edit_phrase = '✎';

    private static string $url_segment = 'go';

    private static $more_phrase = '… &raquo;';

    private static bool $use_default_dashboard_provider = true;

    private static $menu_title = 'Quick-links';

    private static $menu_icon_class = 'font-icon-dashboard';

    private static $menu_priority = 99999;

    private static $colour_options = [];

    private static $max_shortcuts_per_group = 3;

    private static $default_colour_options = [
        '#0D47A1',
        '#01579B',
        '#006064',
        '#004D40',
        '#1B5E20',
        '#33691E',
        '#827717',
        '#F57F17',
        '#FF6F00',
        '#E65100',
        '#BF360C',
        '#3E2723',
        '#212121',
        '#B71C1C',
        '#880E4F',
        '#4A148C',
        '#311B92',
        '#1A237E',
    ];

    /**
     * easy to distinguish colours
     *
     * @var array
     */
    private static $default_colour_options1 = [
        '#F2F3F4',
        '#222222',
        '#F3C300',
        '#875692',
        '#F38400',
        '#A1CAF1',
        '#BE0032',
        '#C2B280',
        '#848482',
        '#008856',
        '#E68FAC',
        '#0067A5',
        '#F99379',
        '#604E97',
        '#F6A600',
        '#B3446C',
        '#DCD300',
        '#882D17',
        '#8DB600',
        '#654522',
        '#E25822',
        '#2B3D26',
    ];

    /**
     * light colours
     *
     * @var array
     */
    private static $default_colour_options3 = [
        '#FFEBEE',
        '#FCE4EC',
        '#F3E5F5',
        '#EDE7F6',
        '#E8EAF6',
        '#E3F2FD',
        '#E1F5FE',
        '#E0F7FA',
        '#E0F2F1',
        '#E8F5E9',
        '#F1F8E9',
        '#F9FBE7',
        '#FFFDE7',
        '#FFF8E1',
        '#FFF3E0',
        '#FBE9E7',
        '#EFEBE9',
        '#FAFAFA',
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // if ($form instanceof HTTPResponse) {
        //     return $form;
        // }

        $this->updateFormWithQuicklinks($form);

        return $form;
    }

    public function updateFormWithQuicklinks($form)
    {
        $shortcuts = $this->getLinksFromImplementor();
        // print_r($shortcuts);
        $html = '';
        $max = $this->Config()->get('max_shortcuts_per_group');
        if (count($shortcuts) > 0) {
            $html = '<div class="grid-wrapper">';

            usort(
                $shortcuts,
                function ($a, $b) {
                    $b['SortOrder'] ?? 0;
                    $a['SortOrder'] ?? 0;
                }
            );

            foreach ($shortcuts as $groupCode => $groupDetails) {
                $colour = '';
                if (! empty($groupDetails['Colour'])) {
                    $colour = 'style="background-color: ' . $groupDetails['Colour'] . '"';
                }
                $icon = '';
                if (! empty($groupDetails['IconClass'])) {
                    $icon = '<i class="' . $groupDetails['IconClass'] . '"></i> ';
                }
                $html .= '
                <div class="grid-cell" ' . $colour . '>
                    <div class="header">
                    <h1>' . $icon . '' . ($groupDetails['Title'] ?? $groupCode) . '</h1>
                    </div>
                    <div class="entries">';
                $items = $groupDetails['Items'] ?? [];
                foreach ($items as $pos => $entry) {
                    if (! empty($entry['Link']) && class_exists($entry['Link'])) {
                        $obj = Injector::inst()->get($entry['Link']);
                        if ($obj instanceof DataObject) {
                            $entry['Link'] = DataObject::get_one($entry['Link'])->CMSEditLink();
                        } else {
                            $entry['Link'] = $obj->Link();
                        }
                    }
                    $html .= $this->createInnerLink($entry, $pos, $items, $max);
                }
                $html .= '</div></div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>Please start editing by making a selection from the left.</p>';
        }
        $kc = (array) $this->Config()->get('colour_options');
        if ($kc === []) {
            $kc = $this->Config()->get('default_colour_options');
        }
        $kcCount = count($kc);
        $colours = '';
        foreach ($kc as $key => $colour) {
            $colours .= ' .grid-wrapper .grid-cell:nth-child(' . $kcCount . 'n+' . ($key + 1) . ') div.header {background-color: ' . $colour . '; color: ' . $this->getFontColor($colour) . '!important;}';
        }
        $html .= '<script>window.setTimeout(dashboardWelcomeQuicklinksSetupInputAndFilter, 500)</script>';
        $html .= '<style>' . $colours . '</style>';
        $form->Fields()->push(LiteralField::create('ShortCuts', $html));
    }

    protected function createInnerLink($entry, $pos, $items, $max)
    {
        $html = '';
        $entry['Class'] = $entry['Class'] ?? '';
        $entry['Class'] .= ($pos > $max) ? ' more-item' : '';
        $html .= $this->makeShortCut($entry)->Field();
        if ($pos > $max && count($items) == $pos + 1) {
            $html .= $this->makeShortCut(
                [
                    'Title' => DashboardWelcomeQuicklinks::get_base_phrase('more'),
                    'Link' => '#',
                    'OnClick' => 'dashboardWelcomeQuicklinksSetupInputAndFilterToggleMore(event); return false;',
                    'Class' => 'more-item-more',
                ]
            )->Field();
        }
        return $html;
    }

    protected function getLinksFromImplementor()
    {
        $array = [];
        $useDefaultDashboard = (bool) $this->config()->get('use_default_dashboard_provider');
        $classNames = ClassInfo::implementorsOf(DashboardWelcomeQuicklinksProvider::class);
        foreach ($classNames as $className) {
            if ($useDefaultDashboard === false && (string) $className === DefaultDashboardProvider::class) {
                continue;
            }
            $array += Injector::inst()->get($className)->provideDashboardWelcomeQuicklinks();
        }
        return $array;
    }

    protected function makeShortCut(array $entry, ?bool $isInsideLink = false): LiteralField|string
    {
        $title = (string) $entry['Title'];
        $tooltip = (string) ($entry['Tooltip'] ?? '');
        if ($title === '+') {
            $tooltip = 'Add new item';
        }
        $link = (string) $entry['Link'];
        $onclick = (string) ($entry['OnClick'] ?? '');
        $script = (string) ($entry['Script'] ?? '');
        $class = (string) ($entry['Class'] ?? '');
        $iconClass = (string) ($entry['IconClass'] ?? '');
        $target = (string) ($entry['Target'] ?? '');
        $insideLink = (array) ($entry['InsideLink'] ?? []);

        $name = preg_replace('#[\W_]+#u', '', $title);
        $html = '';
        if ($onclick !== '' && $onclick !== '0') {
            $onclick = ' onclick="' . $onclick . '"';
        }
        if ($script !== '' && $script !== '0') {
            $script = '<script>' . $script . '</script>';
        }
        $icon = '';
        if (!in_array($iconClass, [null, '', '0'], true)) {
            $icon = '<i class="' . $iconClass . '"></i> ';
        }
        if ($target === '' || $target === '0') {
            $target = '_self';
        }
        $tag = $isInsideLink ? 'span' : 'h2';
        if ($class !== '' && $class !== '0') {
            $class = ' class="' . $class . '"';
        }
        $insideLinkHTML = '';
        if ($insideLink !== []) {
            $insideLink['Class'] = ($insideLink['Class'] ?? '') . ' inside-link';
            $insideLinkHTML = $this->makeShortCut($insideLink, true);
        }
        $target = ' target="' . $target . '"';
        if ($tooltip !== '') {
            $tooltip = 'title="' . htmlspecialchars($tooltip, ENT_QUOTES) . '"';
        }
        if ($link !== '' && $link !== '0') {
            $html = '' . $script . '<' . $tag . '' . $class . '>' . $icon . '<a href="' . $link . '" ' . $target . ' ' . $onclick . ' ' . $tooltip . '>' . $title . '</a>' . $insideLinkHTML . '</' . $tag . '>';
        } else {
            $html = '' . $script . '<' . $tag . '' . $class . ' ' . $tooltip . '>' . $title . '' . $insideLinkHTML . '</' . $tag . '>
            ';
        }
        $html = preg_replace('/\s+/', ' ', $html);
        if ($isInsideLink) {
            return $html;
        } else {
            return LiteralField::create($name, $html);
        }
    }

    /**
     * @return string
     */
    public function Title()
    {
        $app = $this->getApplicationName();
        $siteConfigTitle = SiteConfig::current_site_config()->Title;
        if ($siteConfigTitle) {
            $app = $siteConfigTitle . ' (' . $app . ')';
        }
        return ($section = $this->SectionTitle()) ? sprintf('%s for %s', $section, $app) : $app;
    }

    /**
     * @param bool $unlinked
     * @return ArrayList<ArrayData>
     */
    public function Breadcrumbs($unlinked = false)
    {
        return new ArrayList([
            new ArrayData([
                'Title' => $this->Title(),
                'Link' => ($unlinked) ? false : $this->Link(),
            ]),
        ]);
    }

    protected function getFontColor(string $backgroundColor): string
    {
        // Convert hex color to RGB
        $r = hexdec(substr($backgroundColor, 1, 2));
        $g = hexdec(substr($backgroundColor, 3, 2));
        $b = hexdec(substr($backgroundColor, 5, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // If luminance is greater than 0.5, use black font; otherwise, use white
        return $luminance > 0.5 ? '#222' : '#fff';
    }
}
