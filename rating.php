<?php
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Rating Report");


use \Bitrix\Iblock\PropertyEnumerationTable;
use \Bitrix\Main\Grid\Options as GridOptions;
use \Bitrix\Main\UI\PageNavigation;
use \Bitrix\Main\Page\Asset;
use \Bitrix\Main\UserTable;
 

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
if (!empty($filterData)) {
    foreach ($filterData as $k => $v) {
        if ($k == 'FIND' && !empty($v)) {
            $dateArr['NAME'] = "%" . $v . "%";
        } elseif ($k == 'UF_DEPARTMENT' && !empty($v)) {
            $filter['UF_DEPARTMENT'] = $v;
        } elseif ($k == 'UF_DEPARTMENT' && empty($v)) {
            $filter['!=UF_DEPARTMENT'] = false;
        } elseif ($k == 'CREATED_from' && !empty($v)) {
            $dates['DATE_FROM'] = "AND CREATED >= '" . date("Y-m-d 00:00:00", strtotime($v)) . "' ";
        } elseif ($k == 'CREATED_to' && !empty($v)) {
            $dates['DATE_TO'] = "AND CREATED <= '" . date("Y-m-d 23:59:59", strtotime($v)) . "' ";
        }
    }
} else {
    $filter['!=UF_DEPARTMENT'] = false;
}

foreach ($dates as $key => $value) {
    $where .= $value;
}
if (!empty($nav->getOffset())) {
    $offset = " OFFSET ".$nav->getOffset()." ";
}
if (!empty($nav->getLimit())) {
    $limit = " LIMIT ".$nav->getLimit()." ";
}

// получаем рейтинги фильтрация по датам
$sql = "SELECT OWNER_ID, SUM(TOTAL_VOTES) as RATING
    FROM b_rating_voting 
    WHERE TOTAL_VOTES > 0 " . $where . "
    GROUP BY OWNER_ID
    ORDER BY RATING DESC
    $limit
    $offset
    ";
$res = $DB->query($sql);
while ($thanks = $res->fetch()) {
    $userList[$thanks["OWNER_ID"]]["RATING"] = $thanks["RATING"];
} 

// для расчета постарничной навигации 
$sql = "SELECT COUNT(*) as CNT FROM b_rating_voting WHERE TOTAL_VOTES > 0 " . $where . " GROUP BY OWNER_ID";
$total_user_count = $DB->query($sql)->SelectedRowsCount();
$nav->setRecordCount($total_user_count);

$filter["ID"] = array_keys($userList);  

// получаем список пользователей
$userData = UserTable::getList(array(
    "select" => ["ID", "NAME", "LAST_NAME"],
    "filter" => $filter,
    'count_total' => true
));
while($user = $userData->fetch()) { 
    $printUsers[$user['ID']]['FULL_NAME'] = $user["NAME"] . " " . $user["LAST_NAME"];
} 


// обработка полученных данных
foreach ($userList as $key => $value) {
    if (!empty($printUsers[$key])) {
        $printUsers[$key]['RATING'] = $value['RATING'];
    }
} 
$byRating  = array_column($printUsers, 'RATING'); 
array_multisort($byRating, SORT_DESC, $printUsers);
// array_multisort($RATING, SORT_DESC, $FULL_NAME, SORT_ASC, $printUsers);


// поля поиска/фильтра для заполнения пользователем
$ui_filter = [
    ['id' => 'UF_DEPARTMENT', 'name' => 'Департамент', 'type' => 'text', 'default' => true],
    ['id' => 'CREATED', 'name' => 'Дата создания', 'type' => 'date', 'default' => true]
];
?>
    <div>
        <? 
        // компонента для вывода фильтра/поиска
        $APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
            'FILTER_ID' => $list_id,
            'GRID_ID' => $list_id,
            'FILTER' => $ui_filter,
            'ENABLE_LIVE_SEARCH' => false,
            'ENABLE_LABEL' => true
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
foreach ($printUsers as $key => $row) {
    $name = $row["FULL_NAME"];
    $list[] = [
        'data' => [
            "UF_DEPARTMENT" => '<a href="https://dev-bx24.wtcmoscow.ru/company/personal/user/'.$key.'/">'.$name.'</a>',
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