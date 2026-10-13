<?php ?>
<style>
  .wiz-steps{ display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; }
  .wiz-step{ display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-faint); padding:6px 10px; border-radius:20px; border:1px solid var(--border-soft); }
  .wiz-step.active{ color:var(--accent-bright); border-color:var(--accent-bright); }
  .wiz-step.done{ color:var(--green); border-color:var(--green); }
  .wiz-step-num{ display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:var(--panel-2); font-size:10.5px; font-weight:700; }
  .wiz-step.active .wiz-step-num{ background:var(--accent-bright); color:var(--bg); }
  .wiz-step.done .wiz-step-num{ background:var(--green); color:var(--bg); }
  .wiz-panel{ display:none; }
  .wiz-panel.active{ display:block; }
  .wiz-choice-grid{ display:flex; flex-wrap:wrap; gap:10px; }
  .wiz-choice-card{
    padding:12px 18px; border:1px solid var(--border-soft); border-radius:8px; cursor:pointer;
    font-size:13px; font-weight:600; color:var(--text); text-align:center; min-width:90px;
    background:var(--panel-2); transition:border-color .12s;
  }
  .wiz-choice-card:hover{ border-color:var(--accent-bright); }
  .wiz-choice-card.selected{ border-color:var(--accent-bright); background:var(--accent-bright); color:var(--bg); }
  .wiz-nav{ display:flex; justify-content:space-between; margin-top:20px; gap:10px; }

  .wiz-select-list{
    display:flex; flex-direction:column; gap:6px; max-height:280px; overflow-y:auto;
    border:1px solid var(--border-soft); border-radius:8px; padding:6px; max-width:420px;
  }
  .wiz-select-item{
    padding:10px 12px; font-size:13px; cursor:pointer; border-radius:6px;
    background:var(--panel-2); border:1px solid transparent; transition:border-color .12s, background .12s;
  }
  .wiz-select-item:hover{ border-color:var(--accent-bright); }
  .wiz-select-item.selected{ background:var(--accent-bright); color:var(--bg); border-color:var(--accent-bright); }
  .wiz-select-empty{ padding:12px; font-size:12.5px; color:var(--text-faint); text-align:center; }
  .wiz-select-item-sub{ display:block; font-size:11px; color:var(--text-faint); margin-top:2px; }
  .wiz-select-item.selected .wiz-select-item-sub{ color:var(--bg); opacity:.8; }
  .wiz-selected-chip{
    display:inline-flex; align-items:center; gap:8px; margin-top:10px; padding:7px 12px;
    border:1px solid var(--accent-bright); border-radius:20px; font-size:12.5px; color:var(--accent-bright);
  }
  .wiz-cart-line{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:9px 12px; font-size:12.5px; }
  .wiz-cart-remove{ margin-left:auto; background:none; border:none; color:var(--red); cursor:pointer; font-size:12px; }
  .wiz-search-box{
    width:100%; max-width:420px; box-sizing:border-box; margin-bottom:8px;
    background:var(--bg); border:1px solid var(--border); border-radius:6px;
    padding:8px 11px; color:var(--text); font-size:13px;
  }
  .wiz-modal-card{ max-width:700px; max-height:85vh; overflow-y:auto; }
  .report-jumpnav a{ margin-right:10px; }
</style>

<script>

  var wizPeopleByDept = <?php echo json_encode($peopleByDept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var wizItemTypeList = <?php echo json_encode($itemTypeListPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  var wizState = {
    currentStep: '0',
    deploymentType: 'new',
    departmentId: null,
    departmentName: '',
    recipientName: '',
    replacesDeploymentId: null,
    replacesLabel: '',
    refNumber: '',
    mrNumber: '',
    specKind: '',
    presetSpecs: {},
    cart: []
  };

  function wizEsc(s){
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
  }

  function wizFilterList(listId, query){
    var q = (query || '').toLowerCase().trim();
    var list = document.getElementById(listId);
    if (!list) return;
    var items = list.querySelectorAll('.wiz-select-item');
    var visibleCount = 0;
    items.forEach(function(el){
      var hay = el.getAttribute('data-search') || el.textContent.toLowerCase();
      var match = hay.indexOf(q) !== -1;
      el.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    var emptyEl = list.querySelector('.wiz-filter-empty');
    if (visibleCount === 0 && items.length > 0) {
      if (!emptyEl) {
        emptyEl = document.createElement('div');
        emptyEl.className = 'wiz-select-empty wiz-filter-empty';
        emptyEl.textContent = 'No matches.';
        list.appendChild(emptyEl);
      }
    } else if (emptyEl) {
      emptyEl.remove();
    }
  }

  var wizStepOrder = ['0','1','2','2b','4','5'];

  function wizGoStep(n){
    n = String(n);
    wizState.currentStep = n;
    document.querySelectorAll('.wiz-panel').forEach(function(p){ p.classList.remove('active'); });
    document.getElementById('wizStep' + n).classList.add('active');
    var nIdx = wizStepOrder.indexOf(n);
    document.querySelectorAll('.wiz-step').forEach(function(s){
      var step = s.getAttribute('data-step');
      var idx = wizStepOrder.indexOf(step);
      s.classList.remove('active','done');
      if (step === n) s.classList.add('active');
      else if (idx !== -1 && idx < nIdx) s.classList.add('done');
    });
    if (n === '5') wizRenderReview();
  }

  function openDeployWizard(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    wizResetToStep0();
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
    wizGoStep('0');
  }

  function closeDeployWizard(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  function wizSelectDeploymentType(type){
    wizState.deploymentType = type;
    document.getElementById('wizChoiceNew').classList.toggle('selected', type === 'new');
    document.getElementById('wizChoiceReplacement').classList.toggle('selected', type === 'replacement');
    document.querySelectorAll('.wiz-step-replace').forEach(function(el){
      el.style.display = (type === 'replacement') ? 'flex' : 'none';
    });
    wizGoStep(1);
  }

  function wizOnCategoryChange(){
    var itemCatId = document.getElementById('wizItemCategorySelect').value;
    var kindSelect = document.getElementById('wizCategorySelect');
    if (!itemCatId) {
      kindSelect.innerHTML = '<option value="">— Select category first —</option>';
      kindSelect.disabled = true;
      wizLoadItemsForCategory();
      return;
    }
    var matches = wizItemTypeList.filter(function(t){ return String(t.category_id) === String(itemCatId); });
    if (!matches.length) {
      kindSelect.innerHTML = '<option value="">No kinds of item in this category yet</option>';
      kindSelect.disabled = true;
    } else {
      kindSelect.innerHTML = '<option value="">— Select kind of item —</option>' + matches.map(function(t){
        return '<option value="' + t.id + '" data-spec-kind="' + t.spec_kind + '">' + wizEsc(t.name) + '</option>';
      }).join('');
      kindSelect.disabled = false;
    }
    wizLoadItemsForCategory();
  }

  function wizSelectDepartment(id, name){
    wizState.departmentId = id;
    wizState.departmentName = name;
    wizState.recipientName = '';
    wizState.replacesDeploymentId = null;
    wizState.replacesLabel = '';
    document.getElementById('wizStep2DeptName').textContent = name;
    wizRenderPersonList();
    wizGoStep(2);
  }

  function wizRenderPersonList(){
    var selectEl = document.getElementById('wizPersonSelect');
    var people = wizPeopleByDept[wizState.departmentId] || [];
    if (people.length === 0) {
      selectEl.innerHTML = '<option value="">No faculty/staff on file for this department yet</option>';
      return;
    }
    selectEl.innerHTML = '<option value="">— Select name —</option>' + people.map(function(p){
      return '<option value="' + wizEsc(p.name) + '">' + wizEsc(p.name) + '</option>';
    }).join('');
  }

  function wizSelectPerson(name){
    wizState.recipientName = name;
    wizState.replacesDeploymentId = null;
    wizState.replacesLabel = '';
    if (!name) return;
    if (wizState.deploymentType === 'replacement') {
      document.getElementById('wizStep2bPersonName').textContent = name;
      wizLoadReplaceCandidates();
      wizGoStep('2b');
    } else {
      wizGoStep(4);
    }
  }

  function wizLoadReplaceCandidates(){
    var listEl = document.getElementById('wizReplaceList');
    listEl.innerHTML = '<div class="wiz-select-empty">Loading...</div>';
    var url = 'deployments.php?ajax=active_deployments_for_person&department_id=' + encodeURIComponent(wizState.departmentId) + '&recipient_name=' + encodeURIComponent(wizState.recipientName);
    fetch(url).then(function(r){ return r.json(); }).then(function(data){
      if (!data.ok || !data.items.length) {
        listEl.innerHTML = '<div class="wiz-select-empty">No currently deployed items found for this person.</div>';
        return;
      }
      listEl.innerHTML = data.items.map(function(it){
        var sub = (it.po_number ? 'PO# ' + it.po_number + ' — ' : '') + (it.pn_number ? 'PN# ' + it.pn_number + ' — ' : '') + it.deployed_date + (it.deployed_time ? ' ' + it.deployed_time : '');
        return '<div class="wiz-select-item" data-id="' + it.id + '" data-item-name="' + wizEsc(it.item_name) + '" data-search="' + wizEsc(it.item_name.toLowerCase()) + '">'
          + wizEsc(it.item_name)
          + '<span class="wiz-select-item-sub">' + wizEsc(sub) + '</span>'
          + '</div>';
      }).join('');
      listEl.querySelectorAll('.wiz-select-item').forEach(function(el){
        el.addEventListener('click', function(){
          wizSelectReplaceItem(el.getAttribute('data-id'), el.getAttribute('data-item-name'));
        });
      });
    }).catch(function(){
      listEl.innerHTML = '<div class="wiz-select-empty">Could not load deployed items. Please try again.</div>';
    });
  }

  function wizSelectReplaceItem(deploymentId, itemName){
    wizState.replacesDeploymentId = deploymentId;
    wizState.replacesLabel = itemName;
    document.querySelectorAll('#wizReplaceList .wiz-select-item').forEach(function(el){
      el.classList.toggle('selected', el.getAttribute('data-id') == deploymentId);
    });
    wizGoStep(4);
  }

  function wizOnMrNumberInput(){
    wizState.mrNumber = document.getElementById('wizMrNumber').value.trim();
  }

  function wizLoadItemsForCategory(){
    var kindSelect = document.getElementById('wizCategorySelect');
    var typeId = kindSelect.value;
    var itemSelect = document.getElementById('wizItemSelect');
    var selectedOption = kindSelect.options[kindSelect.selectedIndex];
    wizState.specKind = (typeId && selectedOption) ? (selectedOption.getAttribute('data-spec-kind') || '') : '';
    wizState.parentTypeId = typeId || '';
    wizState.parentTypeName = (typeId && selectedOption) ? selectedOption.text : '';
    wizUpdateComponentSectionVisibility();
    if (!typeId) {
      itemSelect.innerHTML = '<option value="">— Select kind of item first —</option>';
      return;
    }
    itemSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=items_by_category&type_id=' + encodeURIComponent(typeId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.items.length) {
          itemSelect.innerHTML = '<option value="">No items with stock in this kind of item</option>';
          return;
        }
        itemSelect.innerHTML = '<option value="">— Select item —</option>' + data.items.map(function(it){
          return '<option value="' + it.id + '" data-name="' + wizEsc(it.name) + '">' + wizEsc(it.name) + ' — ' + it.available + ' left</option>';
        }).join('');
        wizLoadPnForItem();
      });
  }

  function wizUpdateComponentSectionVisibility(){
    var section = document.getElementById('wizComponentSection');
    var label = document.getElementById('wizComponentParentLabel');
    var components = (wizState.parentTypeId && wizItemTypeComponentsMap[wizState.parentTypeId]) ? wizItemTypeComponentsMap[wizState.parentTypeId] : [];
    if (components.length) {
      section.style.display = 'block';
      label.textContent = '(' + wizState.parentTypeName + ')';
      wizRenderComponentFields(components);
    } else {
      section.style.display = 'none';
    }
  }

  function wizLoadPresetSpecsForItem(){
    var itemSelect = document.getElementById('wizItemSelect');
    var itemId = itemSelect.value;
    wizState.presetSpecs = {};
    if (!itemId) return;
    fetch('deployments.php?ajax=preset_specs_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        wizState.presetSpecs = (data.ok && data.specs) ? data.specs : {};
      });
  }

  function wizLoadPnForItem(){
    var itemSelect = document.getElementById('wizItemSelect');
    var pnSelect = document.getElementById('wizPnNumberSelect');
    var itemId = itemSelect.value;
    if (!itemId) {
      pnSelect.innerHTML = '<option value="">— Select item first —</option>';
      return;
    }
    pnSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=pn_numbers_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.pn_numbers.length) {
          pnSelect.innerHTML = '<option value="">No available unit (PN) left for this item</option>';
          return;
        }
        var taken = wizState.cart.filter(function(l){ return String(l.item_id) === String(itemId); }).map(function(l){ return l.pn_number; });
        var free = data.pn_numbers.filter(function(u){ return taken.indexOf(u.pn) === -1; });
        if (!free.length) {
          pnSelect.innerHTML = '<option value="">All units for this item are already in your cart</option>';
          return;
        }
        pnSelect.innerHTML = '<option value="">— Select unit PN Number —</option>' + free.map(function(u){
          return '<option value="' + wizEsc(u.pn) + '" data-po="' + wizEsc(u.po) + '">' + wizEsc(u.pn) + ' — PO# ' + wizEsc(u.po) + (u.serial ? ' — S/N ' + wizEsc(u.serial) : '') + '</option>';
        }).join('');
      });
  }

  function wizAddCartLine(){
    var itemSelect = document.getElementById('wizItemSelect');
    var itemId = itemSelect.value;
    if (!itemId) { alert('Please select an item first.'); return; }
    var itemName = itemSelect.options[itemSelect.selectedIndex].getAttribute('data-name') || itemSelect.options[itemSelect.selectedIndex].text;
    var pnSelect = document.getElementById('wizPnNumberSelect');
    var pnNum = pnSelect.value;
    if (!pnNum) { alert('Please select a PN Number for this item — it is required.'); return; }
    var poNum = pnSelect.options[pnSelect.selectedIndex].getAttribute('data-po') || '';
    var mrBox = document.getElementById('wizMrNumber');
    var mrNum = mrBox.value.trim();
    if (!mrNum) { alert('Please enter an MR Number — it is required.'); return; }
    var kindSelect = document.getElementById('wizCategorySelect');
    var kindName = kindSelect.options[kindSelect.selectedIndex] ? kindSelect.options[kindSelect.selectedIndex].text : '';
    var itemCatSelect = document.getElementById('wizItemCategorySelect');
    var itemCatName = itemCatSelect.options[itemCatSelect.selectedIndex] ? itemCatSelect.options[itemCatSelect.selectedIndex].text : '';
    var detailsBox = document.getElementById('wizItemDetails');
    var itemDetails = detailsBox ? detailsBox.value.trim() : '';

    var componentLines = [];
    var hasComponents = (wizState.parentTypeId && wizItemTypeComponentsMap[wizState.parentTypeId] && wizItemTypeComponentsMap[wizState.parentTypeId].length);
    if (hasComponents) {
      var check = wizComponentsAllFilled();
      if (!check.ok) {
        alert('Please pick a unit for every required component: ' + check.missing.join(', '));
        return;
      }
      componentLines = wizCollectComponentLines();
    }

    wizState.mrNumber = mrNum;
    wizState.cart.push({
      item_id: itemId, item_name: itemName, category_name: itemCatName + ' — ' + kindName,
      po_number: poNum, pn_number: pnNum, item_details: itemDetails
    });
    wizState.cart = wizState.cart.concat(componentLines);
    pnSelect.innerHTML = '<option value="">— Select item first —</option>';
    if (detailsBox) detailsBox.value = '';
    wizResetComponentFields();
    wizUpdateComponentSectionVisibility();
    wizRenderCart();
  }

  function wizRemoveCartLine(idx){
    wizState.cart.splice(idx, 1);
    wizRenderCart();
  }

  function wizRenderCart(){
    var list = document.getElementById('wizCartList');
    var empty = document.getElementById('wizCartEmpty');
    if (wizState.cart.length === 0) {
      list.innerHTML = '';
      empty.style.display = 'block';
      return;
    }
    empty.style.display = 'none';
    list.innerHTML = wizState.cart.map(function(line, idx){
      return '<div class="wiz-cart-line" style="flex-wrap:wrap;">'
        + '<span><b>' + wizEsc(line.item_name) + '</b></span>'
        + '<span style="color:var(--text-faint);">' + wizEsc(line.category_name) + '</span>'
        + '<span class="idt-batch-po">PO# ' + wizEsc(line.po_number) + '</span>'
        + (line.pn_number ? '<span class="idt-batch-po">PN# ' + wizEsc(line.pn_number) + '</span>' : '')
        + (line.item_details ? '<span class="idt-batch-notes">' + wizEsc(line.item_details) + '</span>' : '')
        + '<button type="button" class="wiz-cart-remove" onclick="wizRemoveCartLine(' + idx + ')">Remove</button>'
        + (line.spec_notes ? '<div class="idt-batch-notes" style="flex-basis:100%; max-height:100px; overflow-y:auto; white-space:pre-wrap; margin-top:4px; padding-top:4px; border-top:1px dashed var(--border);">' + wizEsc(line.spec_notes) + '</div>' : '')
        + '</div>';
    }).join('');
  }

  var wizSpecKindLabels = { pc: 'PC/System Unit', laptop: 'Laptop', projector: 'Projector' };
  var wizItemTypeComponentsMap = <?php echo json_encode($itemTypeComponentsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function wizRenderComponentFields(components){
    var container = document.getElementById('wizComponentFieldsContainer');
    var existing = {};
    document.querySelectorAll('#wizComponentFieldsContainer .wiz-comp-item').forEach(function(el){
      existing[el.getAttribute('data-comp-id')] = el.value;
    });
    var html = components.map(function(comp){
      return '<div class="field-row">'
        + '<div class="field"><label>' + wizEsc(comp.name) + '</label>'
        + '<select class="wiz-comp-item" data-comp-id="' + comp.id + '" data-comp-name="' + wizEsc(comp.name) + '" onchange="wizLoadPnForComponent(' + comp.id + ')">'
        + '<option value="">Loading stock...</option></select></div>'
        + '<div class="field"><label>PN Number</label>'
        + '<select class="wiz-comp-pn" data-comp-id="' + comp.id + '">'
        + '<option value="">— Select ' + wizEsc(comp.name) + ' first —</option></select></div>'
        + '</div>';
    }).join('');
    container.innerHTML = html;
    components.forEach(function(comp){ wizLoadComponentStock(comp.id, existing[comp.id]); });
  }

  function wizLoadComponentStock(typeId, previouslySelected){
    var itemSelect = document.querySelector('.wiz-comp-item[data-comp-id="' + typeId + '"]');
    if (!itemSelect) return;
    itemSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=items_by_category&type_id=' + encodeURIComponent(typeId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.items.length) {
          itemSelect.innerHTML = '<option value="">No stock available — required, restock first</option>';
          return;
        }
        itemSelect.innerHTML = '<option value="">— Select item —</option>' + data.items.map(function(it){
          return '<option value="' + it.id + '" data-name="' + wizEsc(it.name) + '"' + (String(it.id) === previouslySelected ? ' selected' : '') + '>' + wizEsc(it.name) + ' — ' + it.available + ' left</option>';
        }).join('');
        if (previouslySelected) wizLoadPnForComponent(typeId);
      });
  }

  function wizLoadPnForComponent(typeId){
    var itemSelect = document.querySelector('.wiz-comp-item[data-comp-id="' + typeId + '"]');
    var pnSelect = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
    var itemId = itemSelect.value;
    var compName = itemSelect.getAttribute('data-comp-name');
    if (!itemId) {
      pnSelect.innerHTML = '<option value="">— Select ' + wizEsc(compName) + ' first —</option>';
      return;
    }
    pnSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=pn_numbers_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.pn_numbers.length) {
          pnSelect.innerHTML = '<option value="">No available unit (PN) left for this item</option>';
          return;
        }
        pnSelect.innerHTML = '<option value="">— Select unit PN Number —</option>' + data.pn_numbers.map(function(u){
          return '<option value="' + wizEsc(u.pn) + '" data-po="' + wizEsc(u.po) + '">' + wizEsc(u.pn) + ' — PO# ' + wizEsc(u.po) + (u.serial ? ' — S/N ' + wizEsc(u.serial) : '') + '</option>';
        }).join('');
      });
  }

  function wizCollectComponentLines(){
    var lines = [];
    document.querySelectorAll('.wiz-comp-item').forEach(function(itemEl){
      var typeId = itemEl.getAttribute('data-comp-id');
      var compName = itemEl.getAttribute('data-comp-name');
      var itemId = itemEl.value;
      if (!itemId) return;
      var pnEl = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
      var poNum = pnEl.options[pnEl.selectedIndex] ? (pnEl.options[pnEl.selectedIndex].getAttribute('data-po') || '') : '';
      var itemName = itemEl.options[itemEl.selectedIndex].getAttribute('data-name') || itemEl.options[itemEl.selectedIndex].text;
      lines.push({
        item_id: itemId, item_name: itemName, category_name: compName,
        po_number: poNum, pn_number: pnEl.value, item_details: ''
      });
    });
    return lines;
  }

  function wizComponentsAllFilled(){
    var ok = true;
    var missing = [];
    document.querySelectorAll('.wiz-comp-item').forEach(function(itemEl){
      var typeId = itemEl.getAttribute('data-comp-id');
      var compName = itemEl.getAttribute('data-comp-name');
      var pnEl = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
      if (!itemEl.value || !pnEl.value) {
        ok = false;
        missing.push(compName);
      }
    });
    return { ok: ok, missing: missing };
  }

  function wizResetComponentFields(){
    document.getElementById('wizComponentFieldsContainer').innerHTML = '';
    document.getElementById('wizComponentSection').style.display = 'none';
  }

  function wizRenderReview(){
    var wizTypeLabels = { new: 'New Deployment', replacement: 'Deployment for Replacement' };
    document.getElementById('wizReviewType').textContent = wizTypeLabels[wizState.deploymentType] || 'New Deployment';
    document.getElementById('wizReviewDept').textContent = wizState.departmentName || '—';
    var personLabel = wizState.recipientName || '—';
    if (wizState.deploymentType === 'replacement' && wizState.replacesLabel) {
      personLabel += ' (replacing: ' + wizState.replacesLabel + ')';
    }
    document.getElementById('wizReviewPerson').textContent = personLabel;
    var mrReviewEl = document.getElementById('wizReviewMrNumber');
    if (mrReviewEl) mrReviewEl.textContent = wizState.mrNumber || '—';
    document.getElementById('wizReviewCount').textContent = wizState.cart.length;

    var list = document.getElementById('wizReviewCartList');
    if (wizState.cart.length === 0) {
      list.innerHTML = '<div class="idt-empty">No items added.</div>';
    } else {
      list.innerHTML = wizState.cart.map(function(line){
        return '<div class="wiz-cart-line" style="flex-wrap:wrap;">'
          + '<span><b>' + wizEsc(line.item_name) + '</b></span>'
          + '<span style="color:var(--text-faint);">' + wizEsc(line.category_name) + '</span>'
          + '<span class="idt-batch-po">PO# ' + wizEsc(line.po_number) + '</span>'
          + (line.pn_number ? '<span class="idt-batch-po">PN# ' + wizEsc(line.pn_number) + '</span>' : '')
          + (line.item_details ? '<span class="idt-batch-notes">' + wizEsc(line.item_details) + '</span>' : '')
          + (line.spec_notes ? '<div class="idt-batch-notes" style="flex-basis:100%; max-height:120px; overflow-y:auto; white-space:pre-wrap; margin-top:4px; padding-top:4px; border-top:1px dashed var(--border);">' + wizEsc(line.spec_notes) + '</div>' : '')
          + '</div>';
      }).join('');
    }
  }

  function wizConfirmDeployment(){
    if (wizState.cart.length === 0) { alert('Add at least one item before deploying.'); return; }
    if (wizState.deploymentType === 'replacement' && !wizState.replacesDeploymentId) { alert('Please select which deployed item is being replaced.'); return; }

    var btn = document.getElementById('wizConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Deploying...';

    var fd = new FormData();
    fd.append('action', 'submit_deployment_cart');
    fd.append('csrf_token', '<?php echo h(csrf_token()); ?>');
    fd.append('department_id', wizState.departmentId);
    fd.append('recipient_name', wizState.recipientName);
    fd.append('mr_number', wizState.mrNumber);
    fd.append('deployment_type', wizState.deploymentType);
    if (wizState.deploymentType === 'replacement') {
      fd.append('replaces_deployment_id', wizState.replacesDeploymentId);
    }
    fd.append('deployed_date', document.getElementById('wizDeployDate').value);
    fd.append('deployed_time', document.getElementById('wizDeployTime').value);
    fd.append('cart', JSON.stringify(wizState.cart));

    fetch('deployments.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data){
        btn.disabled = false;
        btn.textContent = '✓ Ready for Deployment';
        if (data.ok) {
          document.getElementById('wizDoneSummary').textContent =
            data.deployed + ' item(s) deployed to ' + wizState.departmentName + (wizState.recipientName ? ' — ' + wizState.recipientName : '') + '.';
          document.querySelectorAll('.wiz-panel').forEach(function(p){ p.classList.remove('active'); });
          document.getElementById('wizStepDone').classList.add('active');
        } else {
          alert(data.error || 'Could not complete this deployment.');
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = '✓ Ready for Deployment';
        alert('Something went wrong. Please try again.');
      });
  }

  function wizStartNew(){
    wizResetToStep0();
  }

  function wizResetToStep0(){
    wizState = {
      currentStep: '0', deploymentType: 'new', departmentId: null, departmentName: '', recipientName: '',
      replacesDeploymentId: null, replacesLabel: '', mrNumber: '', specKind: '', presetSpecs: {}, cart: []
    };
    document.getElementById('wizChoiceNew').classList.remove('selected');
    document.getElementById('wizChoiceReplacement').classList.remove('selected');
    document.querySelectorAll('.wiz-step-replace').forEach(function(el){ el.style.display = 'none'; });
    document.getElementById('wizMrNumber').value = '';
    wizResetComponentFields();
    var pnSelectEl = document.getElementById('wizPnNumberSelect');
    if (pnSelectEl) pnSelectEl.innerHTML = '<option value="">— Select PO Number first —</option>';
    var itemDetailsEl = document.getElementById('wizItemDetails');
    if (itemDetailsEl) itemDetailsEl.value = '';
    document.getElementById('wizDeptSelect').value = '';
    document.getElementById('wizPersonSelect').innerHTML = '<option value="">— Select name —</option>';
    document.getElementById('wizReplaceList').innerHTML = '';
    document.getElementById('wizItemCategorySelect').value = '';
    document.getElementById('wizCategorySelect').innerHTML = '<option value="">— Select category first —</option>';
    document.getElementById('wizCategorySelect').disabled = true;
    document.getElementById('wizItemSelect').innerHTML = '<option value="">— Select kind of item first —</option>';
    document.getElementById('wizPnNumberSelect').innerHTML = '<option value="">— Select item first —</option>';
    wizRenderCart();

    fetch('deployments.php?ajax=current_ph_time')
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          document.getElementById('wizDeployDate').value = data.date;
          document.getElementById('wizDeployTime').value = data.time;
        }
      });
    wizGoStep(0);
  }

  var CURRENT_USERNAME = <?php echo json_encode(current_user()['username']); ?>;

  document.querySelectorAll('.return-confirm-form').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var typed = window.prompt('To confirm this return, please type your username (' + CURRENT_USERNAME + '):');
      if (typed === null) return;
      if (typed.trim().toLowerCase() !== CURRENT_USERNAME.toLowerCase()) {
        alert('That username did not match. Return not confirmed.');
        return;
      }
      form.querySelector('.return-confirm-username-field').value = typed.trim();
      form.submit();
    });
  });

  function showDeployDetails(deployId){
    var el = document.getElementById('deployDetails' + deployId);
    if (!el) return;
    var text = el.textContent || el.innerText || 'No details.';
    var backdrop = document.getElementById('deployDetailsBackdrop');
    document.getElementById('deployDetailsBody').textContent = text;
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
  }
  function closeDeployDetails(){
    var backdrop = document.getElementById('deployDetailsBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  document.addEventListener('DOMContentLoaded', function(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    if (backdrop) {
      backdrop.addEventListener('click', closeDeployWizard);
    }
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      var backdrop = document.getElementById('deployWizardBackdrop');
      if (backdrop && backdrop.classList.contains('open')) closeDeployWizard();
      var detailsBackdrop = document.getElementById('deployDetailsBackdrop');
      if (detailsBackdrop && detailsBackdrop.classList.contains('open')) closeDeployDetails();
    }
  });
</script>
