<?php
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("We post likes");


use \Bitrix\Iblock\PropertyEnumerationTable;
use \Bitrix\Main\Grid\Options as GridOptions;
use \Bitrix\Main\UI\PageNavigation;
use \Bitrix\Main\Page\Asset;
use \Bitrix\Main\UserTable;

// CModule::IncludeModule("timeman");
CModule::IncludeModule("main");

Asset::getInstance()->addString('<style>.grayExt{color: #ccc;margin-left: auto;}.flex{display:flex;}</style>');
Asset::getInstance()->addCss('/bitrix/css/main/grid/webform-button.css');

$list_id = 'rating_report';
$userList = [];
$where = '';

// инициализация фильтра
$filterOption = new Bitrix\Main\UI\Filter\Options($list_id);
$filterData = $filterOption->getFilter([]);
$filter = [];

// параметры для таблицы куда будем выводить данные и страничная навигация
$grid_options = new GridOptions($list_id);
$sort = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$nav_params = $grid_options->GetNavParams();
$nav = new Bitrix\Main\UI\PageNavigation($list_id);
$nav->allowAllRecords(false)
    ->setPageSize($nav_params['nPageSize'])
    ->initFromUri();

$offset = $limit = '';

// фильтр обработка данных вводимых пользователем
foreach ($filterData as $k => $v) {
    if ($k == 'FIND' && !empty($v)) {
        $dateArr['NAME'] = "%" . $v . "%";
    } elseif ($k == 'UF_DEPARTMENT' && !empty($v)) {
        $where .= " AND DEPARTMENTS.VALUE_INT=" . $v;
    } elseif ($k == 'CREATED_from' && !empty($v)) {
        $dates['DATE_FROM'] = "AND CREATED >= '" . date("Y-m-d 00:00:00", strtotime($v)) . "' ";
    } elseif ($k == 'CREATED_to' && !empty($v)) {
        $dates['DATE_TO'] = "AND CREATED <= '" . date("Y-m-d 23:59:59", strtotime($v)) . "' ";
    }
}

foreach ($dates as $key => $value) {
    $where .= $value;
}
if (!empty($nav->getOffset())) {
    $offset = " OFFSET " . $nav->getOffset() . " ";
}
if (!empty($nav->getLimit())) {
    $limit = " LIMIT " . $nav->getLimit() . " ";
}

// получаем рейтинги фильтрация по датам
$sql = "SELECT USER_ID AS USER_ID, CREATED, CONCAT(USER.NAME, ' ', USER.LAST_NAME) as FULL_NAME, 
        DEPARTMENTS.VALUE_INT AS DEPARTMENT_ID,
        DEPARTMENT_NAME.NAME AS DEPARTMENTS_NAME, 
        (SELECT COUNT(*) FROM b_rating_vote WHERE USER_ID = USER.ID) AS RATING
    FROM b_rating_vote THANKS
        right JOIN b_user USER ON THANKS.USER_ID = USER.ID
        right JOIN b_utm_user DEPARTMENTS ON USER.ID = DEPARTMENTS.VALUE_ID
        right JOIN b_iblock_section DEPARTMENT_NAME ON DEPARTMENT_NAME.ID = DEPARTMENTS.VALUE_INT 
    WHERE THANKS.USER_ID > 0 $where
    GROUP BY USER_ID
    ORDER BY RATING DESC
";
$sqlFull = $sql . $limit . $offset;

$res = $DB->query($sqlFull);
while ($rating = $res->fetch()) {
    $userList[] = $rating;
}


// для расчета постарничной навигации
$total_user_count = $DB->query($sql)->SelectedRowsCount();
$nav->setRecordCount($total_user_count);

?>
    <div>
        <?php
        // поля поиска/фильтра для заполнения пользователем
        $sql = "SELECT ID, NAME
            FROM b_iblock_section 
            WHERE IBLOCK_ID = 3
            ORDER BY NAME ASC
        ";
        $res = $DB->query($sql);
        while ($field = $res->fetch()) {
            $fieldList[$field["ID"]] = $field["NAME"];
        }
        // echo '<pre>$fieldList<br />'; print_r($fieldList); echo '</pre>';

        $ui_filter = [
            [
                'id' => 'UF_DEPARTMENT',
                'name' => 'Департамент',
                'type' => 'list',
                'default' => true,
                "items" => $fieldList
            ],
            ['id' => 'CREATED', 'name' => 'Дата создания', 'type' => 'date', 'default' => true]
        ];

        // компонента для вывода фильтра/поиска
        $APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
            'FILTER_ID' => $list_id,
            'GRID_ID' => $list_id,
            'FILTER' => $ui_filter,
            'ENABLE_LIVE_SEARCH' => false,
            'ENABLE_LABEL' => true,
            'ENABLE_FIELDS_SEARCH' => true
        ]); ?>
    </div>
    <div style="clear: both;"></div>
    <hr>

<?php
// столбцы таблицы
$columns = [];
$columns[] = ['id' => 'UF_DEPARTMENT', 'name' => 'Сотрудник', 'sort' => 'UF_DEPARTMENT', 'default' => true];
$columns[] = ['id' => 'RATING', 'name' => 'Рейтинг', 'sort' => 'ID', 'default' => true];

// фомримирование ячеек таблицы
foreach ($userList as $row) {
    $list[] = [
        'data' => [
            "UF_DEPARTMENT" => '<a href="https://dev-bx24.wtcmoscow.ru/company/personal/user/' . $row["USER_ID"] . '/">' . $row["FULL_NAME"] . '</a>',
            "RATING" => $row['RATING']
        ]
    ];
}

// компонента для вывода таблицы
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $list_id,
    'COLUMNS' => $columns,
    'ROWS' => $list,
    'NAV_OBJECT' => $nav,
    'AJAX_MODE' => 'N',
    'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
    'PAGE_SIZES' => [
        ['NAME' => '5', 'VALUE' => '5'],
        ['NAME' => '10', 'VALUE' => '10'],
        ['NAME' => '20', 'VALUE' => '20'],
        ['NAME' => '50', 'VALUE' => '50'],
        ['NAME' => '100', 'VALUE' => '100']
    ],
    'AJAX_OPTION_JUMP' => 'N',
    'SHOW_CHECK_ALL_CHECKBOXES' => false,
    'SHOW_ROW_ACTIONS_MENU' => true,
    'TOTAL_ROWS_COUNT' => $total_user_count,
    'SHOW_ROW_CHECKBOXES' => false,
    'SHOW_GRID_SETTINGS_MENU' => true,
    'SHOW_NAVIGATION_PANEL' => true,
    'SHOW_PAGINATION' => true,
    'SHOW_MORE_BUTTON' => true,
    'SHOW_SELECTED_COUNTER' => true,
    'SHOW_TOTAL_COUNTER' => true,
    'SHOW_PAGESIZE' => true,
    'SHOW_ACTION_PANEL' => true,
    'ALLOW_COLUMNS_SORT' => true,
    'ALLOW_COLUMNS_RESIZE' => true,
    'ALLOW_HORIZONTAL_SCROLL' => true,
    'ALLOW_SORT' => false,
    'ALLOW_PIN_HEADER' => true,
    'AJAX_OPTION_HISTORY' => 'N'
]);


require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>