<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>

<script src="/js/jq/jquery.min.js"></script>
<script>
/* the library is loaded asynchronously, so the tree initialisation is deferred:
   the block printed below only assigns window.eyeTreeInit(). */
window.eyeTreeReady = false;
window.eyeTreeInit = null;
$.getScript("/js/jstree/jstree.min.js")
 .done(function () {
        window.eyeTreeReady = true;
        if (typeof window.eyeTreeInit === 'function') { window.eyeTreeInit(); }
  })
 .fail(function (jqxhr, settings, exception) {
        window.location.href = '/admin/devices/index-tree-simple.php';
        });
</script>

<div id="cont">
<br>
<?php
// an icon of a device by its type
function tree_dev_icon($type)
{
    $icons = [
        0 => '/img/router.png',
        1 => '/img/switch16.png',
        2 => '/img/gateway.png',
        3 => '/img/server.png',
        4 => '/img/ap.png',
    ];

    return $icons[(int)$type] ?? '/img/server.png';
}

// the name of a node: the ports of the uplink, the device and its model; a text of a record
// is escaped, the decoration of eye itself stays a markup
function tree_node_text($device, $with_uplink = true)
{
    $title = esc($device['name']);
    if ($with_uplink && (!empty($device['parent_port']) || !empty($device['uplink']))) {
        $title = esc($device['parent_port']) . '->' . esc($device['uplink']) . '&nbsp;' . $title;
    }
    if ($with_uplink && !empty($device['model_name'])) {
        $title .= '&nbsp;::&nbsp;<small><i>[' . esc($device['model_name']) . ']</i></small>';
    }

    return $title;
}

// one node: the link to the device is an attribute of the node, not a anchor inside its name,
// jstree makes the anchor of the node itself out of a_attr
function tree_node($device, $children, $with_uplink = true)
{
    $id = (int)$device['id'];

    return [
        'text' => tree_node_text($device, $with_uplink),
        'icon' => tree_dev_icon($device['type']),
        'id' => (string)$id,
        'state' => ['opened' => true],
        'a_attr' => ['href' => reencodeurl('/admin/devices/editdevice.php?id=' . $id)],
        'children' => $children,
    ];
}

// the devices which run into the given one; the way already walked is kept in mind, a port of
// a device can point back to its own parent and the tree would never end
function tree_branch($device_id, $hash, $processed = [])
{
    $branch = [];
    $device_id = (int)$device_id;
    if (in_array($device_id, $processed, true)) {
        return $branch;
    }
    $processed[] = $device_id;

    foreach ($hash as $device) {
        if ((int)($device['parent_id'] ?? 0) !== $device_id) {
            continue;
        }
        $branch[] = tree_node($device, tree_branch($device['id'], $hash, $processed));
    }

    return $branch;
}

$dSQL = 'SELECT D.*, DM.model_name FROM devices as D, device_models as DM '
    . 'WHERE D.device_model_id=DM.id AND (deleted=0 and device_type<=2)';
$switches = get_records_sql($db_link, $dSQL);
$dev_hash = [];

// every device is bound to the device its uplink port runs into
foreach ($switches as $row) {
    $dev_id = (int)$row['id'];
    $dev_hash[$dev_id] = [
        'id' => $dev_id,
        'name' => $row['device_name'],
        'type' => $row['device_type'],
        'model_name' => $row['model_name'],
        'parent_id' => 0,
        'parent_port' => '',
        'uplink' => '',
    ];

    $uplink = get_record_sql($db_link, 'SELECT * FROM device_ports WHERE uplink = 1 and device_id = ?', [$dev_id]);
    if (empty($uplink) or empty($uplink['target_port_id'])) {
        continue;
    }
    $dev_hash[$dev_id]['uplink'] = $uplink['port_name'];

    $parent = get_record_sql($db_link, 'SELECT * FROM device_ports WHERE device_ports.id = ?', [(int)$uplink['target_port_id']]);
    if (empty($parent) || (int)$parent['device_id'] === $dev_id) {
        // a device which points into itself is a root for us
        continue;
    }

    $dev_hash[$dev_id]['parent_id'] = (int)$parent['device_id'];
    $dev_hash[$dev_id]['parent_port'] = $parent['port_name'];
}

// the roots: the devices nobody is connected to from above
$tree = [];
foreach ($dev_hash as $device) {
    if ((int)$device['parent_id'] !== 0) {
        continue;
    }
    $tree[] = tree_node($device, tree_branch($device['id'], $dev_hash), false);
}

// the whole setup of jstree is written by the json encoder: quotes and tags of a name of a
// device can not break the script of the page, and the text of a node is a text of a record
$jstree = [
    'themes' => ['theme' => 'default', 'dots' => false, 'icons' => false],
    'plugins' => ['themes', 'html_data', 'ui', 'sort'],
    'core' => ['data' => $tree],
];

print '<div id="frmt" class="tree"></div>';
print "\n";
print '<link rel="stylesheet" href="/js/jstree/themes/default/style.min.css">';
print '<script>';
print 'window.eyeTreeInit = function () {$(\'#frmt\').jstree(';
print json_encode($jstree, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
).on('changed.jstree', function (e, data) {
    if (data == null) return '';
    if (data.node == null) return '';
    var href = data.node.a_attr.href;
    if(href == '#') return '';
    var win = window.open(href, "_blank");
});
};
if (window.eyeTreeReady) { window.eyeTreeInit(); }
</script>

<?php
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/footer.php");
?>
