<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$filter = get_record($db_link, 'filter_list', 'id=?', [ $id ]);

// ==================== СОХРАНЕНИЕ ФИЛЬТРА ====================
if (getPOST("editfilter") !== null) {
    $use_ipset = (int)getPOST("f_use_ipset", null, 0);
    if ($use_ipset) {
        $ipset_id = (int)getPOST("f_ipset_id", null, 0);
        $dst = '';
    } else {
        $ipset_id = 0;
        $dst = trim(getPOST("f_dst", null, ''));
    }
    $new = [
        'name'        => trim(getPOST("f_name", null, $filter['name'])),
        'dst'         => $dst,
        'ipset_id'    => $ipset_id > 0 ? $ipset_id : 0,
        'proto'       => trim(getPOST("f_proto", null, '')),
        'dstport'     => str_replace(':', '-', trim(getPOST("f_dstport", null, ''))),
        'srcport'     => str_replace(':', '-', trim(getPOST("f_srcport", null, ''))),
        'description' => trim(getPOST("f_description", null, ''))
    ];

    update_record($db_link, "filter_list", "id = ?", $new, [$id]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_filters_submenu($page_url);

print "<div id=cont>";
print "<br><b>".WEB_title_filter."</b><br>";

$ipsets = get_records_sql($db_link, "SELECT id, name, description FROM ipset_list ORDER BY name");
$has_ipsets = !empty($ipsets);
$current_ipset_id = $filter['ipset_id'] ?? 0;
$use_ipset = ($current_ipset_id > 0) ? 1 : 0;
?>

<form name="def" action="editfilter.php?id=<?php echo $id; ?>" method="post" id="filterForm">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="hidden" name="f_use_ipset" id="f_use_ipset" value="<?php echo $use_ipset; ?>">

    <table class="data" cellspacing="0" cellpadding="4">
        <tr>
            <td><b><?php echo WEB_cell_forename; ?></b></td>
            <td colspan="3"><b><?php echo WEB_cell_description; ?></b></td>
        </tr>
        <tr>
            <td align="left">
                <input type="text" name="f_name" value="<?php echo htmlspecialchars($filter['name']); ?>" size="30">
            </td>
            <td colspan="3">
                <input type="text" name="f_description" value="<?php echo htmlspecialchars($filter['description']); ?>" class="full-width" style="width:100%">
            </td>
            <td><input type="submit" name="editfilter" value="<?php echo WEB_btn_save; ?>"></td>
        </tr>
        <tr>
            <td><b><?php echo WEB_traffic_proto; ?></b></td>
            <td><b><?php echo WEB_traffic_dest_address; ?> (Dst / IPSet)</b></td>
            <td><b><?php echo WEB_traffic_dst_port; ?></b></td>
            <td><b><?php echo WEB_traffic_src_port; ?></b></td>
        </tr>
        <tr>
            <td>
                <input type="text" name="f_proto" value="<?php echo htmlspecialchars($filter['proto']); ?>" size="10">
            </td>
            <td>
                <div style="margin-bottom:8px">
                    <label>
                        <input type="radio" name="dst_mode" value="ip" 
                               <?php if (!$use_ipset) echo 'checked'; ?> 
                               onclick="toggleDstMode('ip')">
                        <?php echo WEB_traffic_dst_subnet; ?>
                    </label>
                    <?php if ($has_ipsets): ?>
                    <label style="margin-left:15px">
                        <input type="radio" name="dst_mode" value="ipset" 
                               <?php if ($use_ipset) echo 'checked'; ?> 
                               onclick="toggleDstMode('ipset')">
                        <?php echo WEB_traffic_dst_ipset; ?>
                    </label>
                    <?php endif; ?>
                </div>
                <div id="dst_ip_container" style="<?php echo $use_ipset ? 'display:none' : 'display:block'; ?>">
                    <input type="text" name="f_dst" id="f_dst" 
                           value="<?php echo htmlspecialchars($filter['dst']); ?>" 
                           size="25" placeholder="192.168.1.0/24">
                </div>
                <div id="dst_ipset_container" style="<?php echo $use_ipset ? 'display:block' : 'display:none'; ?>">
                    <select name="f_ipset_id" id="f_ipset_id" style="width:100%; max-width:300px">
                        <option value="0">-- <?php echo WEB_traffic_select_ipset; ?> --</option>
                        <?php foreach ($ipsets as $ipset): ?>
                        <option value="<?php echo $ipset['id']; ?>" 
                                <?php if ($ipset['id'] == $current_ipset_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($ipset['name']); ?>
                            <?php if (!empty($ipset['description'])): ?>
                                (<?php echo htmlspecialchars(mb_substr($ipset['description'], 0, 40)); ?>...)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </td>
            <td>
                <input type="text" name="f_dstport" value="<?php echo htmlspecialchars(str_replace('-', ':', $filter['dstport'])); ?>" size="10">
            </td>
            <td>
                <input type="text" name="f_srcport" value="<?php echo htmlspecialchars(str_replace('-', ':', $filter['srcport'])); ?>" size="10">
            </td>
        </tr>
    </table>
</form>

<script>
function toggleDstMode(mode) {
    const ipContainer = document.getElementById('dst_ip_container');
    const ipsetContainer = document.getElementById('dst_ipset_container');
    const useIpsetField = document.getElementById('f_use_ipset');
    const dstInput = document.getElementById('f_dst');
    const ipsetSelect = document.getElementById('f_ipset_id');
    if (mode === 'ipset') {
        ipContainer.style.display = 'none';
        ipsetContainer.style.display = 'block';
        useIpsetField.value = '1';
        dstInput.value = '';
        dstInput.removeAttribute('required');
    } else {
        ipContainer.style.display = 'block';
        ipsetContainer.style.display = 'none';
        useIpsetField.value = '0';
        ipsetSelect.value = '0';
    }
};
</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
