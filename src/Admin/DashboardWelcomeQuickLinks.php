<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Admin;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

use SilverStripe\Admin\LeftAndMain;

use SilverStripe\Core\Injector\Injector;

use SilverStripe\Core\ClassInfo;

use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;


class DashboardWelcomeQuicklinks extends LeftAndMain
{

    private static $url_segment = 'go';

    private static $menu_title = 'Quick-links';

    private static $menu_icon_class = 'font-icon-dashboard';

    private static $menu_priority = 99999;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // if ($form instanceof HTTPResponse) {
        //     return $form;
        // }
        // $form->Fields()->removeByName('LastVisited');

        $this->updateFormWithQuicklinks($form);
        return $form;
    }


    /**
     * Only show first element, as the profile form is limited to editing
     * the current member it doesn't make much sense to show the member name
     * in the breadcrumbs.
     *
     * @param bool $unlinked
     *
     * @return ArrayList
     */
    public function Breadcrumbs($unlinked = false)
    {
        $items = parent::Breadcrumbs($unlinked);

        return new ArrayList([$items[0]]);
    }

    public function updateFormWithQuicklinks($form)
    {
        $shortcuts = $this->getLinksFromImplementor();
        if (count($shortcuts)) {
            $html = '<div class="grid-wrapper">';

            usort(
                $shortcuts,
                function($a, $b) {
                    ($a['SortOrder'] ?? 0) <=> ($b['SortOrder'] ?? 0);
                }
            );

            foreach ($shortcuts as $groupCode => $groupDetails) {
                $colour = '';
                if(! empty($groupDetails['Colour'])) {
                    $colour = 'style="background-color: '.$groupDetails['Colour'].'"';
                }
                $icon = '';
                if(! empty($groupDetails['IconClass'])) {
                    $icon = '<i class="'.$groupDetails['IconClass'].'"></i> ';
                }
                $html .= '
                <div class="grid-cell" '.$colour.'>
                    <h1>'.$icon.'' . ($groupDetails['Title'] ?? $groupCode) . '</h1>';
                $items = $groupDetails['Items'] ?? [];
                if(! empty($entry['Link'])&& class_exists($entry['Link'])) {
                    $obj = Injector::inst()->get();
                    if($obj instanceof DataObject) {
                        $entry['Link'] = DataObject::get_one($entry['Link'])->CMSEditLink();
                    } else {
                        $entry['Link'] = $obj->Link();
                    }
                }
                foreach ($items as $entry) {
                    $html .= $this->makeShortCut(
                        $entry['Title'],
                        $entry['Link'],
                        $entry['OnClick'] ?? '',
                        $entry['Script'] ?? '',
                        $entry['Style'] ?? '',
                        $entry['IconClass'] ?? '',
                    )->Field();
                }
                $html .= '</div>';
            }
        }
        $kc = ['#F2F3F4', '#222222', '#F3C300', '#875692', '#F38400', '#A1CAF1', '#BE0032', '#C2B280', '#848482', '#008856', '#E68FAC', '#0067A5', '#F99379', '#604E97', '#F6A600', '#B3446C', '#DCD300', '#882D17', '#8DB600', '#654522', '#E25822', '#2B3D26'];
        $kcCount = count($kc);
        $colours = '';
        foreach($kc as $key => $colour) {
            $colours .= ' .grid-cell:nth-child('.$kcCount.'n+'.($key+1).') {background-color: '.$colour.'55;}';
        }
        $html .= '</div>';
        $html .= '<style>

        .grid-wrapper {
          display: grid;
          grid-template-columns: repeat( auto-fit, minmax(300px, 1fr) );;
          grid-gap: 10px;
        }

        .grid-cell {
          padding: 20px;
          font-size: 150%;
          border-radius: 1rem;
          border: 1px dashed #004e7f55;
        }
        '.$colours.'
        .grid-cell * {
            color: #222!important;
        }
        .grid-cell h1 {
            border-bottom: 1px solid #777
        }
        </style>';
        $form->Fields()->push(LiteralField::create('ShortCuts', $html));
    }

    protected function getLinksFromImplementor()
    {
        $array = [];
        $classNames = ClassInfo::implementorsOf(DashboardWelcomeQuickLinksProvider::class);
        foreach ($classNames as $className) {
            $array += Injector::inst()->get($className)->provideDashboardWelcomeQuickLinks();
        }

        return $array;
    }


    protected function makeShortCut(string $title, string $link, ?string $onclick = '', ?string $script = '', ?string $style = '', ?string $iconClass)
    {
        $name = preg_replace('#[\W_]+#u', '', $title);
        $html = '';
        if ($onclick) {
            $onclick = ' onclick="' . $onclick . '"';
        }
        if ($script) {
            $script = '<script>' . $script . '</script>';
        }
        $icon = '';
        if(! empty($iconClass)) {
            $icon = '<i class="'.$iconClass.'"></i> ';
        }
        if($link) {
            $html = '
            ' . $script . '
            <h2 style="' . $style . '">
                &raquo; '.$icon.'<a href="' . $link . '" id="' . $name . '" target="_blank" ' . $onclick . '>' . $title . '</a>
            </h2>';
        } else {
            $html = '
            ' . $script . '
            <p>
                &raquo; '.$title . '
            </p>
            ';
        }
        if($style) {
            $html .= '<style>'.$style.'</style>';
        }
        return LiteralField::create(
            $name,
            $html
        );
    }


}
