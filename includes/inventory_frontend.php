<?php ?>
<style>
  .expand-row{ display:none; }
  .expand-row.open-row{ display:table-row; }
  .clickable-row{ cursor:pointer; }
  .clickable-row:hover{ background:var(--panel-2); }
  .po-multi-badge{
    display:inline-block; padding:3px 9px; border-radius:20px;
    border:1px solid var(--accent-bright); color:var(--accent-bright);
    font-size:11px; font-weight:600; white-space:nowrap;
  }
  .clickable-row:hover .po-multi-badge{ background:var(--accent-bright); color:var(--bg); }
  .item-details-card{
    background:var(--panel); border:1px solid var(--border); border-radius:8px;
    padding:24px 26px; max-width:640px; width:100%; max-height:85vh; overflow-y:auto;
    position:relative;
  }
  .idt-title{ font-size:16px; font-weight:600; color:var(--text); margin:0 0 2px; padding-right:20px; }
  .idt-sub{ font-size:12px; color:var(--text-faint); margin:0 0 16px; }
  .idt-stats{ display:grid; grid-template-columns:repeat(auto-fit, minmax(100px,1fr)); gap:10px; margin-bottom:18px; }
  .idt-stat{ background:var(--panel-2); border:1px solid var(--border-soft); border-radius:6px; padding:10px 12px; }
  .idt-stat-label{ font-size:10.5px; letter-spacing:.04em; text-transform:uppercase; color:var(--text-faint); margin-bottom:4px; }
  .idt-stat-value{ font-size:16px; font-weight:600; color:var(--text); }
  .idt-stat-clickable{ cursor:pointer; transition:background .12s; }
  .idt-stat-clickable:hover{ background:var(--panel); border-color:var(--accent-bright); }
  .idt-expand{ margin-bottom:14px; }
  .idt-section-title{ font-family:var(--font-mono); font-size:11.5px; letter-spacing:.05em; color:var(--accent-bright); text-transform:uppercase; margin:18px 0 8px; }
  .idt-batches{ border:1px solid var(--border-soft); border-radius:6px; overflow:hidden; }
  .idt-batch-row-wrap{ border-top:1px solid var(--border-soft); }
  .idt-batch-row-wrap:first-child{ border-top:none; }
  .idt-batch-row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:9px 12px; font-size:12.5px; }
  .idt-batch-draws{ padding:0 12px 9px; font-size:11px; color:var(--text-faint); }
  .idt-batch-tag{ font-size:10px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:2px 7px; border-radius:10px; }
  .idt-batch-tag-new{ background:var(--green); color:#08130d; }
  .idt-batch-tag-old{ background:var(--text-faint); color:#08130d; }
  .idt-batch-date{ color:var(--text-dim); min-width:82px; }
  .idt-batch-qty{ font-weight:600; color:var(--text); }
  .idt-batch-po{ font-family:var(--font-mono); font-size:11.5px; color:var(--accent-bright); }
  .idt-batch-notes{ color:var(--text-faint); flex:1; min-width:140px; }
  .idt-batch-by{ color:var(--text-faint); font-size:11px; margin-left:auto; }
  .idt-empty{ color:var(--text-faint); font-size:12.5px; padding:10px 0; }
</style>

<script>

  var itemDetailsData = <?php
    $detailsPayload = [];
    foreach ($allItems as $i) {
        $left = max(0, $i['quantity_total'] - $i['deployed_qty']);
        $isLow = $left <= $i['low_stock_threshold'];
        $batches = [];
        foreach (($batchesByItem[$i['id']] ?? []) as $b) {
            $draws = [];
            foreach (($drawsByBatch[$b['id']] ?? []) as $dr) {
                $draws[] = [
                    'qty'      => (int)$dr['quantity'],
                    'dept'     => $dr['dept_name'],
                    'date'     => date('M j, Y', strtotime($dr['deployed_date'])),
                    'returned' => !empty($dr['returned_date']),
                ];
            }
            $batches[] = [
                'date'    => date('M j, Y', strtotime($b['batch_date'])),
                'time'    => !empty($b['batch_time']) ? date('g:i A', strtotime($b['batch_time'])) : '',
                'qty'     => (int)$b['quantity'],
                'pn'      => $b['pn_number'] ?: '',
                'po'      => $b['po_number'] ?? '',
                'notes'   => $b['notes'] ?: '',
                'by'      => $b['added_by_name'] ?: 'Unknown User',
                'draws'   => $draws,
                'units'   => array_values(array_filter($serialsByBatch[$b['id']] ?? [], function($u){ return $u['pn'] !== '' || $u['sn'] !== ''; })),
            ];
        }
        $remarks = [];
        foreach (($remarksByItem[$i['id']] ?? []) as $r) {
            $remarks[] = [
                'date' => date('M j, Y', strtotime($r['remark_date'])),
                'text' => $r['remark'],
                'by'   => $r['added_by_name'] ?: 'Unknown User',
            ];
        }
        $deployedList = [];
        foreach (($activeDeploysByItem[$i['id']] ?? []) as $a) {
            $deployedList[] = [
                'qty'   => (int)$a['quantity_deployed'],
                'type'  => $a['deployment_type'] ?: 'new',
                'dept'  => $a['dept_name'],
                'to'    => $a['recipient_name'] ?: '',
                'date'  => date('M j, Y', strtotime($a['deployed_date'])),
                'time'  => !empty($a['deployed_time']) ? date('g:i A', strtotime($a['deployed_time'])) : '',
            ];
        }
        $detailsPayload[$i['id']] = [
            'name'       => $i['item_name'],
            'kind'       => $i['type_name'],
            'total'      => (int)$i['quantity_total'],
            'deployed'   => (int)$i['deployed_qty'],
            'left'       => $left,
            'status'     => $isLow ? 'Low Stock' : 'Good Stock',
            'statusGood' => !$isLow,
            'dateAdded'  => date('M j, Y', strtotime($i['date_added'])),
            'batches'    => $batches,
            'remarks'    => $remarks,
            'deployedList' => $deployedList,
        ];
    }
    echo json_encode($detailsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  ?>;

  function escHtml(s){
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
  }

  function openItemDetails(itemId){
    var data = itemDetailsData[itemId];
    if (!data) return;

    var pnList = [];
    var poList = [];
    data.batches.forEach(function(b){
      (b.units || []).forEach(function(u){ if (u.pn && pnList.indexOf(u.pn) === -1) pnList.push(u.pn); });
      if (b.po && poList.indexOf(b.po) === -1) poList.push(b.po);
    });
    var pnSummary = pnList.length ? pnList.map(escHtml).join(', ') : '—';
    var poSummary = poList.length ? poList.map(escHtml).join(', ') : '—';

    var statusClass = data.statusGood ? 'b-green' : 'b-red';

    var batchesHtml;
    if (data.batches.length === 0) {
      batchesHtml = '<div class="idt-empty">No batch history recorded yet.</div>';
    } else {
      var multi = data.batches.length > 1;
      batchesHtml = '<div class="idt-batches">' + data.batches.map(function(b, idx){
        var drawsLine = '';
        if (b.draws && b.draws.length) {
          drawsLine = '<div class="idt-batch-draws">Deployed from this batch: ' + b.draws.map(function(d){
            return d.qty + ' to ' + escHtml(d.dept) + ' on ' + escHtml(d.date) + (d.returned ? ' (returned)' : '');
          }).join('; ') + '</div>';
        }
        var serialsLine = '';
        if (b.units && b.units.length) {
          serialsLine = '<div class="idt-batch-draws" style="max-height:120px; overflow-y:auto;">' + b.units.map(function(u){
            return '<div>' + (u.pn ? 'PN #' + escHtml(u.pn) : 'No PN') + (u.sn ? ' — S/N ' + escHtml(u.sn) : '') + (u.deployed ? ' <span style="color:var(--accent-bright);">(deployed)</span>' : '') + '</div>';
          }).join('') + '</div>';
        }
        var tag = '';
        if (multi && idx === 0) tag = '<span class="idt-batch-tag idt-batch-tag-new">Newest stock</span>';
        else if (multi && idx === data.batches.length - 1) tag = '<span class="idt-batch-tag idt-batch-tag-old">Oldest stock</span>';
        var whenLabel = escHtml(b.date) + (b.time ? ' · ' + escHtml(b.time) : '');
        return '<div class="idt-batch-row-wrap">'
          + '<div class="idt-batch-row">'
          + tag
          + '<span class="idt-batch-date">' + whenLabel + '</span>'
          + '<span class="idt-batch-qty">+' + b.qty + '</span>'
          + (b.po ? '<span class="idt-batch-po">PO #' + escHtml(b.po) + '</span>' : '<span class="idt-batch-po" style="color:var(--text-faint);">No PO #</span>')
          + (b.notes ? '<span class="idt-batch-notes">' + escHtml(b.notes) + '</span>' : '')
          + (b.by ? '<span class="idt-batch-by">by ' + escHtml(b.by) + '</span>' : '')
          + '</div>'
          + drawsLine
          + serialsLine
          + '</div>';
      }).join('') + '</div>';
    }

    var deployedHtml;
    if (!data.deployedList || data.deployedList.length === 0) {
      deployedHtml = '<div class="idt-empty">Nothing currently deployed.</div>';
    } else {
      deployedHtml = '<div class="idt-batches">' + data.deployedList.map(function(d){
        var typeBadge = d.type === 'replacement' ? '<span class="badge b-accent">Replacement</span>' : '<span class="badge">New</span>';
        return '<div class="idt-batch-row-wrap"><div class="idt-batch-row">'
          + typeBadge
          + '<span class="idt-batch-date">' + escHtml(d.date) + (d.time ? ' · ' + escHtml(d.time) : '') + '</span>'
          + '<span class="idt-batch-qty">×' + d.qty + '</span>'
          + '<span class="idt-batch-notes">' + escHtml(d.dept) + (d.to ? ' — ' + escHtml(d.to) : '') + '</span>'
          + '</div></div>';
      }).join('') + '</div>';
    }

    var totalBreakdownHtml = '<div class="idt-batches">' + data.batches.map(function(b){
      return '<div class="idt-batch-row-wrap"><div class="idt-batch-row">'
        + '<span class="idt-batch-date">' + escHtml(b.date) + (b.time ? ' · ' + escHtml(b.time) : '') + '</span>'
        + '<span class="idt-batch-qty">+' + b.qty + '</span>'
        + (b.po ? '<span class="idt-batch-po">PO #' + escHtml(b.po) + '</span>' : '')
        + '</div></div>';
    }).join('') + '</div>';

    var leftHtml = '<div class="idt-empty">' + data.left + ' unit(s) currently available (Total − Deployed).</div>';

    var remarksHtml;
    if (data.remarks.length === 0) {
      remarksHtml = '<div class="idt-empty">No remarks logged yet.</div>';
    } else {
      remarksHtml = '<div class="idt-batches">' + data.remarks.map(function(r){
        return '<div class="idt-batch-row-wrap"><div class="idt-batch-row">'
          + '<span class="idt-batch-date">' + escHtml(r.date) + '</span>'
          + '<span class="idt-batch-notes">' + escHtml(r.text) + '</span>'
          + (r.by ? '<span class="idt-batch-by">by ' + escHtml(r.by) + '</span>' : '')
          + '</div></div>';
      }).join('') + '</div>';
    }

    var html = ''
      + '<h3 class="idt-title">' + escHtml(data.name) + '</h3>'
      + '<p class="idt-sub">' + escHtml(data.kind) + '</p>'
      + '<div class="idt-stats">'
      +   '<div class="idt-stat idt-stat-clickable" onclick="idtToggle(\'idt-total\')"><div class="idt-stat-label">Total Qty ▾</div><div class="idt-stat-value">' + data.total + '</div></div>'
      +   '<div class="idt-stat idt-stat-clickable" onclick="idtToggle(\'idt-deployed\')"><div class="idt-stat-label">Deployed ▾</div><div class="idt-stat-value">' + data.deployed + '</div></div>'
      +   '<div class="idt-stat idt-stat-clickable" onclick="idtToggle(\'idt-left\')"><div class="idt-stat-label">Left ▾</div><div class="idt-stat-value">' + data.left + '</div></div>'
      +   '<div class="idt-stat"><div class="idt-stat-label">Status</div><div class="idt-stat-value"><span class="badge ' + statusClass + '">' + escHtml(data.status) + '</span></div></div>'
      +   '<div class="idt-stat"><div class="idt-stat-label">Date Added</div><div class="idt-stat-value" style="font-size:13px;">' + escHtml(data.dateAdded) + '</div></div>'
      +   '<div class="idt-stat"><div class="idt-stat-label">PN #</div><div class="idt-stat-value" style="font-size:12.5px; font-family:var(--font-mono);">' + pnSummary + '</div></div>'
      +   '<div class="idt-stat"><div class="idt-stat-label">PO #</div><div class="idt-stat-value" style="font-size:12.5px; font-family:var(--font-mono);">' + poSummary + '</div></div>'
      + '</div>'
      + '<div id="idt-total" class="idt-expand" style="display:none;"><div class="idt-section-title">Total Qty — Batch Breakdown</div>' + totalBreakdownHtml + '</div>'
      + '<div id="idt-deployed" class="idt-expand" style="display:none;"><div class="idt-section-title">Currently Deployed</div>' + deployedHtml + '</div>'
      + '<div id="idt-left" class="idt-expand" style="display:none;"><div class="idt-section-title">Left In Stock</div>' + leftHtml + '</div>'
      + '<div class="idt-section-title">Batch History (PN # / PO #)</div>'
      + batchesHtml
      + '<div class="idt-section-title">Remarks</div>'
      + remarksHtml;

    document.getElementById('itemDetailsBody').innerHTML = html;
    var backdrop = document.getElementById('itemDetailsBackdrop');
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
  }

  function idtToggle(id){
    var el = document.getElementById(id);
    if (!el) return;
    var isOpen = el.style.display !== 'none';
    document.querySelectorAll('.idt-expand').forEach(function(e){ e.style.display = 'none'; });
    el.style.display = isOpen ? 'none' : 'block';
  }

  function closeItemDetails(){
    var backdrop = document.getElementById('itemDetailsBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  document.getElementById('itemDetailsBackdrop').addEventListener('click', closeItemDetails);
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeItemDetails();
  });

  var restockRowsWrap = document.getElementById('restockItemRows');
  var restockRowTemplate = document.getElementById('restockRowTemplate');
  var restockRowCount = 0;

  var restockSpecKindItemTypeIds = <?php
    $typeIdToSpecKey2 = [];
    $itemTypes->data_seek(0);
    while ($t = $itemTypes->fetch_assoc()) {
        $sk = spec_schema_key_for_type_name($t['name']);
        if ($sk !== null) $typeIdToSpecKey2[(int)$t['id']] = $sk;
    }
    echo json_encode($typeIdToSpecKey2, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  ?>;
  var restockSpecKindLabels = { pc: 'PC/System Unit', laptop: 'Laptop', projector: 'Projector' };
  var restockSpecSchema = <?php echo json_encode(SPEC_SCHEMA, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function restockRenderSpecs(rowEl){
    var idx = rowEl.getAttribute('data-row-index');
    var typeId = rowEl.querySelector('.restock-type-select').value;
    var specsWrap = rowEl.querySelector('.restock-specs-wrap');
    var specKey = restockSpecKindItemTypeIds[typeId];
    var schema = specKey ? restockSpecSchema[specKey] : null;
    if (!schema) {
      specsWrap.innerHTML = '';
      return;
    }
    var kindLabel = restockSpecKindLabels[specKey] || 'Item';
    var keys = Object.keys(schema);
    var html = '<label style="display:block; margin:8px 0 6px; font-size:12.5px; color:var(--text-dim);">Unit Specifications (optional preset for this ' + kindLabel + ' model)</label>'
      + '<div style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:10px;">';
    for (var i = 0; i < keys.length; i += 2) {
      html += '<div class="field-row" style="margin-bottom:6px;">';
      for (var j = i; j < Math.min(i + 2, keys.length); j++) {
        var key = keys[j];
        var meta = schema[key];
        html += '<div class="field"><label style="font-size:11.5px;">' + meta[0] + '</label>'
          + '<input type="text" name="spec_values[' + idx + '][' + key + ']" placeholder="' + meta[1] + '" style="width:100%; box-sizing:border-box; background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:6px 9px; color:var(--text); font-size:12.5px;"></div>';
      }
      html += '</div>';
    }
    html += '</div>';
    specsWrap.innerHTML = html;
  }

  function restockAddRow(){
    var frag = restockRowTemplate.content.cloneNode(true);
    var rowEl = frag.querySelector('.restock-item-row');
    rowEl.setAttribute('data-row-index', restockRowCount);
    restockRowsWrap.appendChild(frag);
    var addedRow = restockRowsWrap.querySelector('.restock-item-row[data-row-index="' + restockRowCount + '"]');
    var qtyInput = addedRow.querySelector('.restock-qty-input');
    qtyInput.addEventListener('input', function(){ restockRenderSerials(addedRow); });
    restockRenderSerials(addedRow);
    restockRowCount++;
    restockUpdateRemoveButtons();
  }

  function restockRemoveRow(btn){
    var row = btn.closest('.restock-item-row');
    if (row) row.remove();
    restockUpdateRemoveButtons();
  }

  function restockUpdateRemoveButtons(){
    var rows = restockRowsWrap.querySelectorAll('.restock-item-row');
    rows.forEach(function(r){
      var btn = r.querySelector('.restock-remove-row');
      btn.style.display = rows.length > 1 ? '' : 'none';
    });
  }

  function restockRenderSerials(rowEl){
    var idx = rowEl.getAttribute('data-row-index');
    var qty = Math.max(1, parseInt(rowEl.querySelector('.restock-qty-input').value, 10) || 1);
    var list = rowEl.querySelector('.restock-serials-list');
    var existingPn = Array.prototype.slice.call(list.querySelectorAll('.restock-unit-pn')).map(function(i){ return i.value; });
    var existingSn = Array.prototype.slice.call(list.querySelectorAll('.restock-unit-sn')).map(function(i){ return i.value; });
    var inputStyle = 'width:100%; box-sizing:border-box; background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:6px 9px; color:var(--text); font-size:12.5px;';
    var html = '<div style="max-height:220px; overflow-y:auto;">';
    for (var u = 0; u < qty; u++) {
      var pnVal = existingPn[u] ? ' value="' + existingPn[u].replace(/"/g, '&quot;') + '"' : '';
      var snVal = existingSn[u] ? ' value="' + existingSn[u].replace(/"/g, '&quot;') + '"' : '';
      html += '<div class="field-row" style="margin-bottom:6px;">'
        + '<div class="field"><input type="text" class="restock-unit-pn" name="unit_pn[' + idx + '][]" placeholder="Unit ' + (u+1) + ' PN Number (required)" required' + pnVal + ' style="' + inputStyle + '"></div>'
        + '<div class="field"><input type="text" class="restock-unit-sn" name="serial_numbers[' + idx + '][]" placeholder="Unit ' + (u+1) + ' serial number (optional)"' + snVal + ' style="' + inputStyle + '"></div>'
        + '</div>';
    }
    html += '</div>';
    list.innerHTML = html;
  }

  if (restockRowsWrap) {
    restockAddRow();
  }
</script>
