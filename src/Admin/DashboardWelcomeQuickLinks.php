<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Admin;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

use SilverStripe\Admin\LeftAndMain;

use SilverStripe\Core\Injector\Injector;

use SilverStripe\Core\ClassInfo;

use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;

use SilverStripe\ORM\ArrayList;


class DashboardWelcomeQuicklinks extends LeftAndMain
{

    private static $url_segment = 'go';

    private static $menu_title = 'Quicklinks';

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
            $form->Fields()->push(HeaderField::create('UsefulLinks', 'Short-cuts', 1));

            usort(
                $shortcuts,
                function($a, $b) {
                    ($a['SortOrder'] ?? 0) <=> ($b['SortOrder'] ?? 0);
                }
            );

            foreach ($shortcuts as $group => $groupDetails) {
                $html .= '
                <div class="grid-cell">
                    <h1>' . ($groupDetails['Title'] ?? 'Links') . '</h1>';
                $items = $groupDetails['Items'] ?? [];
                if(! empty($entry['Link'])&& class_exists($entry['Link'])) {
                    $entry['Link'] = Injector::inst()->get($entry['Link'])->Link();
                }
                foreach ($items as $entry) {
                    $html .= $this->makeShortCut(
                        $entry['Title'],
                        $entry['Link'],
                        $entry['OnClick'] ?? '',
                        $entry['Script'] ?? '',
                        $entry['Style'] ?? '',
                    )->Field();
                }
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        $html .= '<style>

        .grid-wrapper {
          display: grid;
          grid-template-columns: repeat( auto-fit, minmax(300px, 1fr) );;
          grid-gap: 10px;
        }

        .grid-cell {
          background-color: #e9f0f4;
          padding: 20px;
          font-size: 150%;
          border-radius: 1rem;
          border: 1px dashed #004e7f55;
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


    protected function makeShortCut(string $title, string $link, ?string $onclick = '', ?string $script = '', ?string $style = '')
    {
        $name = preg_replace('#[\W_]+#u', '', $title);
        $html = '';
        if ($onclick) {
            $onclick = ' onclick="' . $onclick . '"';
        }
        if ($script) {
            $script = '<script>' . $script . '</script>';
        }
        if($link) {
            $html = '
            ' . $script . '
            <h2 style="' . $style . '">
                &raquo; <a href="' . $link . '" id="' . $name . '" target="_blank" ' . $onclick . '>' . $title . '</a>
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
