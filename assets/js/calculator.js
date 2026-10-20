/* ============================================================================
   House Construction Cost Calculator — calculation engine + UI wiring
   Runs entirely in the visitor's browser. Calculator inputs are never sent
   to any server (see the Privacy Policy page). The only network requests
   this file makes are lazily loading the PDF library when the visitor clicks
   "Download PDF Report", and the optional estimate feedback — only when the
   visitor clicks a feedback button.
   ============================================================================ */
(function () {
  "use strict";

  // Bail out quietly on pages that don't include the calculator shortcode.
  var form = document.getElementById("calc-form");
  if (!form) return;

  /* ==========================================================================
     EDITABLE RATE ASSUMPTIONS — PAKISTAN, INDICATIVE 2026 MARKET DATA
     --------------------------------------------------------------------------
     Defaults shown in the form's "Advanced" rate fields. Visitors can override
     cement / steel / brick / sand / crush / boundary-wall / approval-fee
     numbers directly in the UI. Everything else below (quality quantity
     factors, city factors, labour rates, finishing split, design-complexity
     factors, room extras) is not exposed in the UI and can be re-tuned here
     to adapt the calculator for a different city mix or market.

     Research basis (Sept 2026, multiple Pakistani contractor/marketplace
     sources): cement ~PKR 1,375-1,610 per 50kg bag, Grade-60 steel ~PKR
     260-265 per kg (raw mill rate; ~PKR 275-290/kg delivered incl. wastage
     and binding wire), bricks ~PKR 18,000-25,000 per 1000, grey structure
     ~PKR 1,800-3,400 per sq ft, complete house ~PKR 2,600-8,500 per sq ft
     depending on quality and city, boundary wall (7ft brick, plastered,
     coping) ~PKR 2,200-5,800 per running ft depending on quality/finish,
     and town-planning map/building approval fees varying widely by
     development authority (LDA/CDA/DHA/society) — modelled here as an
     editable, approximate per-Marla allowance rather than a fixed number.
     ========================================================================== */

  // DEFAULT Marla/Kanal standard — used for every SEO guide page's static
  // examples/FAQ figures and as this form's default. Pakistan has more than
  // one Marla standard in real-world use (225 / 250 / 272 / 272.25 sq ft);
  // the "Marla / Kanal Standard" dropdown below lets a visitor switch, and
  // 1 Kanal is always exactly 20 Marla under whichever standard is picked.
  var MARLA_TO_SQFT_DEFAULT = 225; // 1 Marla = 225 sq ft (most common)
  var MARLA_TO_SQFT = MARLA_TO_SQFT_DEFAULT;
  var KANAL_TO_SQFT = 20 * MARLA_TO_SQFT_DEFAULT; // 1 Kanal = 20 Marla

  var BASE_QTY_PER_SQFT = {
    cementBags: 0.42,
    steelKg: 3.6,
    bricks: 27.0, // v1.9.0: was 8; a real take-off is about 25–30 per sq ft
    sandCft: 1.1,
    crushCft: 0.85
  };

  var QUALITY = {
    economy: { label: "Economy", qtyFactor: 0.88, greyLabour: 520, finishRate: 900, finishLabourShare: 0.42, turnkeyRate: 280, wallRatePerRft: 2400 },
    standard: { label: "Standard", qtyFactor: 1.00, greyLabour: 680, finishRate: 1450, finishLabourShare: 0.45, turnkeyRate: 480, wallRatePerRft: 3200 },
    premium: { label: "Premium", qtyFactor: 1.15, greyLabour: 900, finishRate: 2300, finishLabourShare: 0.47, turnkeyRate: 900, wallRatePerRft: 4200 },
    luxury: { label: "Luxury", qtyFactor: 1.35, greyLabour: 1180, finishRate: 3300, finishLabourShare: 0.50, turnkeyRate: 1500, wallRatePerRft: 5600 }
  };

  // Design complexity — a differentiator vs. plain grey/complete/turnkey-only
  // calculators: a simple, efficient box-shaped layout costs less per sq ft
  // than an architecturally complex design with cantilevers, curves and
  // double-height spaces, even at the same quality tier.
  var DESIGN_COMPLEXITY = {
    efficient: { label: "Efficient / Simple Layout", labourFactor: 0.95, qtyBump: 0.98 },
    typical: { label: "Typical Layout", labourFactor: 1.00, qtyBump: 1.00 },
    complex: { label: "Architecturally Complex", labourFactor: 1.15, qtyBump: 1.06 }
  };

  var CITY_FACTORS = {
    lahore: 1.00, karachi: 0.98, islamabad: 1.08, rawalpindi: 0.95, faisalabad: 0.90,
    multan: 0.88, peshawar: 0.87, gujranwala: 0.89, sialkot: 0.90, hyderabad: 0.88,
    sargodha: 0.87, other: 0.90
  };

  var FINISH_MATERIAL_SPLIT = { tiles: 0.30, plumbing: 0.16, electrical: 0.15, paint: 0.12, woodwork: 0.20, misc: 0.07 };

  var ROOM_UNIT_COST = {
    economy: { bathroom: 70000, kitchen: 250000 },
    standard: { bathroom: 110000, kitchen: 380000 },
    premium: { bathroom: 180000, kitchen: 650000 },
    luxury: { bathroom: 280000, kitchen: 1100000 }
  };

  var BASEMENT_MATERIAL_SHARE = 0.65, BASEMENT_LABOUR_SHARE = 0.35;
  var TURNKEY_MATERIAL_SHARE = 0.55, TURNKEY_LABOUR_SHARE = 0.45;
  var ROOM_MATERIAL_SHARE = 0.60, ROOM_LABOUR_SHARE = 0.40;
  var WALL_MATERIAL_SHARE = 0.62, WALL_LABOUR_SHARE = 0.38;

  // Default material rates come from PHP (hcc_default_calc_rates) so every
  // page, the form fields and this file share one source. The literal values
  // are only a fallback for a page that forgot to print window.hccCalc.
  var DEFAULT_RATES = (window.hccCalc && window.hccCalc.rates) || { cement: 1600, steel: 275, bricksPer1000: 20000, sand: 90, crush: 130, approvalPerMarla: 18000 };

  // Upper limits for the form — anything above is almost certainly a typo.
  var LIMITS = { plotSqft: 200000, coveredArea: 200000, basementArea: 100000, wallLength: 5000, rooms: 30,
    cement: 20000, steel: 2000, bricksPer1000: 200000, sand: 2000, crush: 2000, approvalPerMarla: 2000000 };

  var BASEMENT_EXTRA_FACTOR = 1.45; // basement ~45% more per sq ft than ground-floor grey structure
  var STOREY_COMPLEXITY_PER_FLOOR = 0.03; // +3% per storey above the 1st

  // Boundary-wall running-feet auto-suggestion: for a square plot, perimeter
  // = 4 x sqrt(area). Real residential plots in Pakistan are rectangular
  // (commonly close to a 1:2 depth:width ratio), which works out to roughly
  // 4.4 x sqrt(area) instead of 4x — this constant is only a starting
  // SUGGESTION; the field is fully editable once you know your plot's exact
  // dimensions or have a site/boundary survey.
  var WALL_PERIMETER_FACTOR = 4.4;

  /* --------------------------------------------------------------------------
     Display helpers
  -------------------------------------------------------------------------- */
  function formatPKR(n) {
    n = Math.round(n);
    return "PKR " + n.toLocaleString("en-US");
  }
  // Same rules as hcc_lakh_crore() in PHP: round first, then pick the unit.
  function toLacCroreLabel(n) {
    n = Math.round(n);
    var lakh = Math.round(n / 10000) / 10;
    if (lakh >= 100) return "≈ " + trimNum((n / 10000000).toFixed(2)) + " Crore";
    if (n >= 100000) return "≈ " + trimNum(lakh.toFixed(1)) + " Lakh";
    return "";
  }
  function trimNum(s) { return s.indexOf(".") >= 0 ? s.replace(/0+$/, "").replace(/\.$/, "") : s; }
  // Reads the live "Marla / Kanal Standard" dropdown, if present on the page.
  // Falls back to the 225 sq ft default used by every static guide page.
  function getMarlaToSqft() {
    var el = document.getElementById("marla-standard");
    var v = el ? parseFloat(el.value) : NaN;
    return v > 0 ? v : MARLA_TO_SQFT_DEFAULT;
  }
  function sqftFromPlot(size, unit, marlaToSqftOverride) {
    size = parseFloat(size) || 0;
    var m = marlaToSqftOverride > 0 ? marlaToSqftOverride : getMarlaToSqft();
    if (unit === "marla") return size * m;
    if (unit === "kanal") return size * 20 * m; // 1 Kanal = 20 Marla, whichever standard is selected
    if (unit === "sqyd") return size * 9; // Karachi plots: 1 sq yd = 9 sq ft
    return size;
  }
  // v1.9.0: interpolated ratio (mirrors hcc_coverage_ratio in PHP) so a
  // bigger plot never gets less covered area than a smaller one.
  function coverageRatio(p) {
    if (p <= 1125) return 0.85;
    if (p <= 1575) return 0.85 - (p - 1125) / 450 * 0.10;
    if (p <= 2250) return 0.75;
    if (p <= 4500) return 0.75 - (p - 2250) / 2250 * 0.10;
    if (p <= 9000) return 0.65 - (p - 4500) / 4500 * 0.10;
    return 0.55;
  }
  function suggestCoveredArea(plotSqft, storeys) {
    plotSqft = Math.max(0, plotSqft);
    return Math.round(plotSqft * coverageRatio(plotSqft) * Math.max(1, storeys));
  }
  function suggestWallLength(plotSqft) {
    return Math.round(WALL_PERIMETER_FACTOR * Math.sqrt(Math.max(0, plotSqft)));
  }

  /* --------------------------------------------------------------------------
     computeEstimate — THE CORE CALCULATION ENGINE (pure function).
     Used by the interactive form, the live 5/7/10 Marla & 1 Kanal example
     table, and the quick-answer strip, so nothing on the page can drift out
     of sync with the calculator itself.
  -------------------------------------------------------------------------- */
  function computeEstimate(inputs) {
    var coveredArea = Math.max(0, inputs.coveredArea);
    var storeys = Math.max(1, inputs.storeys);
    var hasBasement = !!inputs.hasBasement;
    var basementArea = hasBasement ? Math.max(0, inputs.basementArea) : 0;
    var constructionType = inputs.constructionType;
    var q = QUALITY[inputs.quality] || QUALITY.standard;
    var dc = DESIGN_COMPLEXITY[inputs.designComplexity] || DESIGN_COMPLEXITY.typical;
    var cityFactor = CITY_FACTORS[inputs.city] != null ? CITY_FACTORS[inputs.city] : CITY_FACTORS.other;
    var matCityFactor = 1 + (cityFactor - 1) * 0.35;
    var storeyFactor = 1 + Math.max(0, storeys - 1) * STOREY_COMPLEXITY_PER_FLOOR;
    var complexityLabourFactor = storeyFactor * dc.labourFactor;
    var rates = {};
    ["cement", "steel", "bricksPer1000", "sand", "crush", "approvalPerMarla"].forEach(function (k) {
      var v = inputs.rates && inputs.rates[k] != null ? +inputs.rates[k] : DEFAULT_RATES[k];
      rates[k] = Math.max(0, isFinite(v) ? v : 0);
    });
    // v1.9.0: the city material factor only adjusts rates still at their
    // default. A rate the visitor typed is their own local quote.
    function mrate(k) { return Math.abs(rates[k] - DEFAULT_RATES[k]) < 0.0001 ? rates[k] * matCityFactor : rates[k]; }

    // v1.9.0: storey factor is part of the quantity, so quantity × rate = line cost.
    var qf = coveredArea * q.qtyFactor * dc.qtyBump * storeyFactor;
    var qty = {
      cementBags: qf * BASE_QTY_PER_SQFT.cementBags,
      steelKg: qf * BASE_QTY_PER_SQFT.steelKg,
      bricks: qf * BASE_QTY_PER_SQFT.bricks,
      sandCft: qf * BASE_QTY_PER_SQFT.sandCft,
      crushCft: qf * BASE_QTY_PER_SQFT.crushCft
    };

    var itemCement = qty.cementBags * mrate("cement");
    var itemSteel = qty.steelKg * mrate("steel");
    var itemBricks = qty.bricks * (mrate("bricksPer1000") / 1000);
    var itemSandCrush = qty.sandCft * mrate("sand") + qty.crushCft * mrate("crush");
    var itemStructureLabour = coveredArea * q.greyLabour * cityFactor * complexityLabourFactor;

    var greyStructureCost = itemCement + itemSteel + itemBricks + itemSandCrush + itemStructureLabour;

    var greyRatePerSqft = coveredArea > 0 ? greyStructureCost / coveredArea : 0;
    var basementCost = hasBasement ? basementArea * greyRatePerSqft * BASEMENT_EXTRA_FACTOR : 0;

    var greyStructureSubtotal = greyStructureCost + basementCost;

    // Basement material quantities, in proportion to its share of grey cost.
    var basementQtyFactor = coveredArea > 0 ? 1 + (basementArea * BASEMENT_EXTRA_FACTOR) / coveredArea : 1;
    Object.keys(qty).forEach(function (k) { qty[k] *= basementQtyFactor; });

    // A basement is finished like the floors above it.
    var finishArea = coveredArea + basementArea;
    var itemTiles = 0, itemPlumbing = 0, itemElectrical = 0, itemPaint = 0, itemWoodwork = 0,
      itemMiscFinish = 0, itemFinishLabour = 0, itemRoomExtras = 0;
    var finishingSubtotal = 0;

    if (constructionType === "complete" || constructionType === "turnkey") {
      var finishMaterialTotal = finishArea * q.finishRate * (1 - q.finishLabourShare) * matCityFactor;
      var finishLabourTotal = finishArea * q.finishRate * q.finishLabourShare * cityFactor * dc.labourFactor;

      itemTiles = finishMaterialTotal * FINISH_MATERIAL_SPLIT.tiles;
      itemPlumbing = finishMaterialTotal * FINISH_MATERIAL_SPLIT.plumbing;
      itemElectrical = finishMaterialTotal * FINISH_MATERIAL_SPLIT.electrical;
      itemPaint = finishMaterialTotal * FINISH_MATERIAL_SPLIT.paint;
      itemWoodwork = finishMaterialTotal * FINISH_MATERIAL_SPLIT.woodwork;
      itemMiscFinish = finishMaterialTotal * FINISH_MATERIAL_SPLIT.misc;
      itemFinishLabour = finishLabourTotal;

      var baselineBathrooms = Math.ceil(Math.max(0, inputs.bedrooms) / 2) || 1;
      var extraBathrooms = Math.max(0, inputs.bathrooms - baselineBathrooms);
      var extraKitchens = Math.max(0, inputs.kitchens - 1);
      var kitchenFactor = constructionType === "turnkey" ? 1 : 0.5;
      var roomCost = ROOM_UNIT_COST[inputs.quality] || ROOM_UNIT_COST.standard;
      itemRoomExtras = extraBathrooms * roomCost.bathroom + extraKitchens * roomCost.kitchen * kitchenFactor;

      finishingSubtotal = itemTiles + itemPlumbing + itemElectrical + itemPaint + itemWoodwork + itemMiscFinish + itemFinishLabour + itemRoomExtras;
    }

    var itemTurnkey = constructionType === "turnkey" ? finishArea * q.turnkeyRate * cityFactor * dc.labourFactor : 0;

    // Optional boundary wall (off by default) — a "hidden cost head" that many
    // simple calculators omit entirely.
    var hasWall = !!inputs.hasBoundaryWall;
    var wallLength = hasWall ? Math.max(0, inputs.wallLength) : 0;
    var itemBoundaryWall = hasWall ? wallLength * q.wallRatePerRft * ((matCityFactor * WALL_MATERIAL_SHARE) + (cityFactor * WALL_LABOUR_SHARE)) : 0;

    // Optional government map/building approval (NOC) fee allowance — varies
    // greatly by development authority/society; modelled as an editable
    // per-Marla amount and always shown with that caveat in the UI.
    var hasApproval = !!inputs.hasApprovalFee;
    var marlaToSqft = inputs.marlaToSqft > 0 ? inputs.marlaToSqft : MARLA_TO_SQFT_DEFAULT;
    var plotMarla = inputs.plotSqft > 0 ? inputs.plotSqft / marlaToSqft : 0;
    var itemApprovalFee = hasApproval ? plotMarla * rates.approvalPerMarla : 0;

    var grandTotal = greyStructureSubtotal + finishingSubtotal + itemTurnkey + itemBoundaryWall + itemApprovalFee;
    var costPerSqft = finishArea > 0 ? (greyStructureSubtotal + finishingSubtotal + itemTurnkey) / finishArea : 0;

    var materialCostTotal = itemCement + itemSteel + itemBricks + itemSandCrush
      + itemTiles + itemPlumbing + itemElectrical + itemPaint + itemWoodwork + itemMiscFinish
      + basementCost * BASEMENT_MATERIAL_SHARE + itemTurnkey * TURNKEY_MATERIAL_SHARE + itemRoomExtras * ROOM_MATERIAL_SHARE
      + itemBoundaryWall * WALL_MATERIAL_SHARE;
    // Approval/NOC fees are a government service charge, not a material or a
    // trade-labour cost — they are grouped into "Labour & Services" so that
    // Material Cost + Labour Cost still reconciles exactly to Grand Total.
    var labourCostTotal = itemStructureLabour + itemFinishLabour
      + basementCost * BASEMENT_LABOUR_SHARE + itemTurnkey * TURNKEY_LABOUR_SHARE + itemRoomExtras * ROOM_LABOUR_SHARE
      + itemBoundaryWall * WALL_LABOUR_SHARE + itemApprovalFee;

    return {
      coveredArea: coveredArea, basementArea: basementArea, totalArea: finishArea, grandTotal: grandTotal, costPerSqft: costPerSqft,
      greyStructureSubtotal: greyStructureSubtotal, finishingSubtotal: finishingSubtotal, turnkeyAddon: itemTurnkey,
      materialCostTotal: materialCostTotal, labourCostTotal: labourCostTotal,
      basementCost: basementCost, boundaryWallCost: itemBoundaryWall, approvalFeeCost: itemApprovalFee,
      items: {
        cement: itemCement, steel: itemSteel, bricks: itemBricks, sandCrush: itemSandCrush,
        structureLabour: itemStructureLabour, basement: basementCost,
        tiles: itemTiles, plumbing: itemPlumbing, electrical: itemElectrical, paint: itemPaint,
        woodwork: itemWoodwork, miscFinish: itemMiscFinish, finishLabour: itemFinishLabour,
        roomExtras: itemRoomExtras, turnkey: itemTurnkey, boundaryWall: itemBoundaryWall, approvalFee: itemApprovalFee
      },
      qty: qty,
      constructionType: constructionType, quality: inputs.quality, city: inputs.city
    };
  }

  /* --------------------------------------------------------------------------
     DOM references
  -------------------------------------------------------------------------- */
  var $ = function (id) { return document.getElementById(id); };
  var plotSizeInput = $("plot-size");
  var plotUnitSelect = $("plot-unit");
  var plotSqftHint = $("plot-sqft-hint");
  var marlaStandardSelect = $("marla-standard");
  var marlaStandardWrap = $("marla-standard-wrap");
  var coveredAreaInput = $("covered-area");
  var coveredAreaHint = $("covered-area-hint");
  var storeysSelect = $("storeys");
  var hasBasementCheckbox = $("has-basement");
  var basementAreaWrap = $("basement-area-wrap");
  var basementAreaInput = $("basement-area");
  var hasWallCheckbox = $("has-boundary-wall");
  var wallLengthWrap = $("wall-length-wrap");
  var wallLengthInput = $("wall-length");
  var hasApprovalCheckbox = $("has-approval-fee");
  var resultsSection = $("results");
  var summaryGrid = $("summary-grid");
  var costBreakdownBody = $("cost-breakdown-body");
  var qtyBody = $("qty-body");
  var formError = $("form-error");
  var coveredAreaTouched = false;
  var wallLengthTouched = false;

  function updatePlotHintAndSuggestion() {
    var unit = plotUnitSelect.value;
    if (marlaStandardWrap) marlaStandardWrap.hidden = (unit === "sqft" || unit === "sqyd");
    var marlaStd = getMarlaToSqft();
    var plotSqft = sqftFromPlot(plotSizeInput.value, unit, marlaStd);
    var unitNote = unit === "marla" ? " (1 Marla = " + marlaStd + " sq ft)"
      : unit === "kanal" ? " (1 Kanal = " + (20 * marlaStd).toLocaleString("en-US") + " sq ft)"
      : unit === "sqyd" ? " (1 sq yd = 9 sq ft)" : "";
    plotSqftHint.textContent = plotSqft > 0
      ? ("= " + Math.round(plotSqft).toLocaleString("en-US") + " sq ft" + unitNote)
      : "Enter a plot size";
    if (!coveredAreaTouched) {
      var storeys = parseInt(storeysSelect.value, 10) || 1;
      var suggestion = suggestCoveredArea(plotSqft, storeys);
      coveredAreaInput.value = suggestion;
      coveredAreaHint.textContent = "Auto-suggested from plot size & storeys (" + Math.round(plotSqft).toLocaleString("en-US") + " sq ft plot × " + storeys + " storey" + (storeys > 1 ? "s" : "") + ") — edit if you have an exact drawing.";
    }
    if (wallLengthInput && !wallLengthTouched) {
      wallLengthInput.value = suggestWallLength(plotSqft);
    }
    updateQuickAnswer();
  }
  plotSizeInput.addEventListener("input", updatePlotHintAndSuggestion);
  plotUnitSelect.addEventListener("change", updatePlotHintAndSuggestion);
  storeysSelect.addEventListener("change", updatePlotHintAndSuggestion);
  if (marlaStandardSelect) marlaStandardSelect.addEventListener("change", updatePlotHintAndSuggestion);
  coveredAreaInput.addEventListener("input", function () { coveredAreaTouched = true; });
  if (wallLengthInput) wallLengthInput.addEventListener("input", function () { wallLengthTouched = true; });

  document.querySelectorAll(".preset-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      // "Custom Size" is a discoverability shortcut, not a preset value: the
      // Plot Size field already accepts any number (including e.g. 2.5
      // Marla) — this just focuses/selects it so a visitor knows they can
      // type their own exact size instead of picking one of the pills.
      if (btn.dataset.custom) {
        plotSizeInput.focus();
        plotSizeInput.select();
        return;
      }
      if (btn.dataset.marla) { plotUnitSelect.value = "marla"; plotSizeInput.value = btn.dataset.marla; }
      if (btn.dataset.kanal) { plotUnitSelect.value = "kanal"; plotSizeInput.value = btn.dataset.kanal; }
      coveredAreaTouched = false;
      wallLengthTouched = false;
      updatePlotHintAndSuggestion();
    });
  });

  if (hasBasementCheckbox) {
    hasBasementCheckbox.addEventListener("change", function () {
      basementAreaWrap.hidden = !hasBasementCheckbox.checked;
      if (hasBasementCheckbox.checked && (!basementAreaInput.value || basementAreaInput.value === "0")) {
        var plotSqft = sqftFromPlot(plotSizeInput.value, plotUnitSelect.value);
        var storeys = parseInt(storeysSelect.value, 10) || 1;
        basementAreaInput.value = Math.round(suggestCoveredArea(plotSqft, storeys) / storeys);
      }
    });
  }
  if (hasWallCheckbox) {
    hasWallCheckbox.addEventListener("change", function () {
      wallLengthWrap.hidden = !hasWallCheckbox.checked;
    });
  }

  updatePlotHintAndSuggestion();

  /* --------------------------------------------------------------------------
     Quick-answer strip: "grey structure & complete house cost per sq ft in
     <city> today" — updates live as the visitor changes city/quality, so the
     page answers the "just tell me the number" searcher immediately, the
     way competitor pages do, without needing a page reload.
  -------------------------------------------------------------------------- */
  function updateQuickAnswer() {
    var qaGrey = $("qa-grey-val");
    var qaComplete = $("qa-complete-val");
    var qaCity = $("qa-city-label");
    if (!qaGrey || !qaComplete) return;
    var cityVal = $("city") ? $("city").value : "lahore";
    var defaultRates = DEFAULT_RATES;
    var grey = computeEstimate({
      coveredArea: 1000, storeys: 1, hasBasement: false, basementArea: 0, hasBoundaryWall: false, wallLength: 0,
      hasApprovalFee: false, plotSqft: 0, constructionType: "grey", quality: "standard", designComplexity: "typical",
      city: cityVal, bedrooms: 3, bathrooms: 3, kitchens: 1, rates: defaultRates
    });
    var complete = computeEstimate({
      coveredArea: 1000, storeys: 1, hasBasement: false, basementArea: 0, hasBoundaryWall: false, wallLength: 0,
      hasApprovalFee: false, plotSqft: 0, constructionType: "complete", quality: "standard", designComplexity: "typical",
      city: cityVal, bedrooms: 3, bathrooms: 3, kitchens: 1, rates: defaultRates
    });
    qaGrey.textContent = formatPKR(grey.costPerSqft) + " / sq ft";
    qaComplete.textContent = formatPKR(complete.costPerSqft) + " / sq ft";
    if (qaCity) qaCity.textContent = cityLabel(cityVal);
  }
  if ($("city")) $("city").addEventListener("change", updateQuickAnswer);
  updateQuickAnswer();

  /* --------------------------------------------------------------------------
     Read + validate inputs
  -------------------------------------------------------------------------- */
  function num(el, fallback) {
    var v = el ? parseFloat(el.value) : NaN;
    return isFinite(v) ? v : (fallback || 0);
  }
  function readInputs() {
    var ctEl = form.querySelector('input[name="construction-type"]:checked');
    var qEl = form.querySelector('input[name="quality"]:checked');
    var constructionType = ctEl ? ctEl.value : "complete";
    var quality = qEl ? qEl.value : "standard";
    var designComplexityEl = form.querySelector('input[name="design-complexity"]:checked');
    var marlaToSqft = getMarlaToSqft();
    var plotSqft = sqftFromPlot(plotSizeInput.value, plotUnitSelect.value, marlaToSqft);
    return {
      city: $("city").value,
      plotSize: parseFloat(plotSizeInput.value) || 0,
      plotUnit: plotUnitSelect.value,
      plotSqft: plotSqft,
      marlaToSqft: marlaToSqft,
      coveredArea: parseFloat(coveredAreaInput.value) || 0,
      storeys: parseInt(storeysSelect.value, 10) || 1,
      hasBasement: hasBasementCheckbox ? hasBasementCheckbox.checked : false,
      basementArea: basementAreaInput ? (parseFloat(basementAreaInput.value) || 0) : 0,
      hasBoundaryWall: hasWallCheckbox ? hasWallCheckbox.checked : false,
      wallLength: wallLengthInput ? (parseFloat(wallLengthInput.value) || 0) : 0,
      hasApprovalFee: hasApprovalCheckbox ? hasApprovalCheckbox.checked : false,
      constructionType: constructionType,
      quality: quality,
      designComplexity: designComplexityEl ? designComplexityEl.value : "typical",
      bedrooms: parseInt($("bedrooms").value, 10) || 0,
      bathrooms: parseInt($("bathrooms").value, 10) || 0,
      kitchens: parseInt($("kitchens").value, 10) || 0,
      rates: {
        cement: num($("rate-cement")),
        steel: num($("rate-steel")),
        bricksPer1000: num($("rate-bricks")),
        sand: num($("rate-sand")),
        crush: num($("rate-crush")),
        approvalPerMarla: $("rate-approval") ? num($("rate-approval")) : DEFAULT_RATES.approvalPerMarla
      }
    };
  }

  // v1.9.0: the form is novalidate, so negative or absurd numbers are caught here.
  var RATE_LABELS = { cement: "Cement rate", steel: "Steel rate", bricksPer1000: "Bricks rate", sand: "Sand rate", crush: "Crush rate", approvalPerMarla: "Approval fee" };
  function validateInputs(inputs) {
    if (!inputs.coveredArea || inputs.coveredArea < 100) return "Please enter a valid covered area of at least 100 sq ft.";
    if (inputs.coveredArea > LIMITS.coveredArea) return "Covered area looks too large — please check the number (maximum " + LIMITS.coveredArea.toLocaleString("en-US") + " sq ft).";
    if (!inputs.plotSize || inputs.plotSize <= 0) return "Please enter a valid plot size.";
    if (inputs.plotSqft > LIMITS.plotSqft) return "Plot size looks too large — please check the number and unit.";
    if (inputs.bedrooms < 0 || inputs.bathrooms < 0 || inputs.kitchens < 0 || inputs.bedrooms > LIMITS.rooms || inputs.bathrooms > LIMITS.rooms || inputs.kitchens > LIMITS.rooms) return "Please enter room counts between 0 and " + LIMITS.rooms + ".";
    if (inputs.hasBasement && inputs.basementArea > LIMITS.basementArea) return "Basement area looks too large — please check the number.";
    if (inputs.hasBoundaryWall && inputs.wallLength > LIMITS.wallLength) return "Boundary wall length looks too large — please check the number.";
    for (var k in RATE_LABELS) {
      if (inputs.rates[k] < 0) return RATE_LABELS[k] + " cannot be negative.";
      if (inputs.rates[k] > LIMITS[k]) return RATE_LABELS[k] + " looks too high — please check the number.";
    }
    if (inputs.hasBasement && (!inputs.basementArea || inputs.basementArea <= 0)) return "Please enter a basement area, or uncheck “Include Basement”.";
    if (inputs.hasBoundaryWall && (!inputs.wallLength || inputs.wallLength <= 0)) return "Please enter a boundary wall length, or uncheck “Include Boundary Wall”.";
    return null;
  }

  /* --------------------------------------------------------------------------
     Rendering
  -------------------------------------------------------------------------- */
  var ITEM_LABELS = {
    cement: "Cement", steel: "Steel / Saria", bricks: "Bricks", sandCrush: "Sand & Crush (Aggregate)",
    structureLabour: "Structure Labour (Mason, Steel-fixer, Helper)", basement: "Basement Construction (extra)",
    tiles: "Tiles / Flooring", plumbing: "Plumbing (Pipework & Fixtures)", electrical: "Electrical (Wiring & Fittings)",
    paint: "Paint & Wall Finishing", woodwork: "Woodwork (Doors, Windows, Frames)", miscFinish: "Other Finishing Materials",
    finishLabour: "Finishing Labour (Tiler, Electrician, Plumber, Painter)", roomExtras: "Extra Bathroom(s) / Kitchen(s) Fit-out",
    turnkey: "Turnkey Fit-out (Cabinets, Wardrobes, Fixtures)", boundaryWall: "Boundary Wall Construction",
    approvalFee: "Government Map / Building Approval (NOC) Allowance"
  };
  var GREY_KEYS = ["cement", "steel", "bricks", "sandCrush", "structureLabour", "basement"];
  var FINISH_KEYS = ["tiles", "plumbing", "electrical", "paint", "woodwork", "miscFinish", "finishLabour", "roomExtras"];

  function renderResults(result, inputs, noScroll) {
    summaryGrid.innerHTML = "";
    var tiles = [
      { lab: "Total Estimated Cost", val: formatPKR(result.grandTotal), sub: toLacCroreLabel(result.grandTotal), total: true },
      { lab: "Cost Per Sq Ft", val: formatPKR(result.costPerSqft), sub: "based on " + Math.round(result.totalArea).toLocaleString("en-US") + " sq ft " + (result.basementArea > 0 ? "incl. basement" : "covered area") },
      { lab: "Grey Structure Cost", val: formatPKR(result.greyStructureSubtotal), sub: result.basementCost > 0 ? "incl. basement" : "" },
      { lab: "Finishing Cost", val: inputs.constructionType === "grey" ? "Not Included" : formatPKR(result.finishingSubtotal), sub: "" },
      { lab: "Estimated Material Cost", val: formatPKR(result.materialCostTotal), sub: "" },
      { lab: "Estimated Labour & Services Cost", val: formatPKR(result.labourCostTotal), sub: "" }
    ];
    if (inputs.constructionType === "turnkey") tiles.push({ lab: "Turnkey Fit-out Add-on", val: formatPKR(result.turnkeyAddon), sub: "" });
    if (result.boundaryWallCost > 0) tiles.push({ lab: "Boundary Wall", val: formatPKR(result.boundaryWallCost), sub: "" });
    if (result.approvalFeeCost > 0) tiles.push({ lab: "Approval / NOC Allowance", val: formatPKR(result.approvalFeeCost), sub: "editable estimate" });

    tiles.forEach(function (t) {
      var d = document.createElement("div");
      d.className = "summary-tile" + (t.total ? " total" : "");
      d.innerHTML = '<div class="lab">' + t.lab + '</div><div class="val">' + t.val + '</div>' + (t.sub ? '<div class="sub">' + t.sub + '</div>' : "");
      summaryGrid.appendChild(d);
    });

    costBreakdownBody.innerHTML = "";
    function addRow(label, amount, cls) {
      var tr = document.createElement("tr");
      if (cls) tr.className = cls;
      tr.innerHTML = "<td>" + label + "</td><td class=\"num\">" + formatPKR(amount) + "</td>";
      costBreakdownBody.appendChild(tr);
    }
    GREY_KEYS.forEach(function (k) {
      if (k === "basement" && result.items.basement <= 0) return;
      addRow(ITEM_LABELS[k], result.items[k]);
    });
    addRow("Grey Structure Subtotal", result.greyStructureSubtotal, "subtotal");

    if (inputs.constructionType !== "grey") {
      FINISH_KEYS.forEach(function (k) {
        if (k === "roomExtras" && result.items.roomExtras <= 0) return;
        addRow(ITEM_LABELS[k], result.items[k]);
      });
      addRow("Finishing Subtotal", result.finishingSubtotal, "subtotal");
    }
    if (inputs.constructionType === "turnkey") addRow(ITEM_LABELS.turnkey, result.items.turnkey);
    if (result.items.boundaryWall > 0) addRow(ITEM_LABELS.boundaryWall, result.items.boundaryWall);
    if (result.items.approvalFee > 0) addRow(ITEM_LABELS.approvalFee, result.items.approvalFee);
    addRow("Grand Total (" + labelForType(inputs.constructionType) + ")", result.grandTotal, "grand");

    qtyBody.innerHTML = "";
    function addQty(label, val) {
      var tr = document.createElement("tr");
      tr.innerHTML = "<td>" + label + "</td><td class=\"num\">" + val + "</td>";
      qtyBody.appendChild(tr);
    }
    addQty("Cement", Math.round(result.qty.cementBags).toLocaleString("en-US") + " bags (50kg)");
    addQty("Steel / Saria", Math.round(result.qty.steelKg).toLocaleString("en-US") + " kg (" + (result.qty.steelKg / 1000).toFixed(2) + " tons)");
    addQty("Bricks", Math.round(result.qty.bricks).toLocaleString("en-US") + " nos.");
    addQty("Sand", Math.round(result.qty.sandCft).toLocaleString("en-US") + " cft");
    addQty("Crush / Bajri", Math.round(result.qty.crushCft).toLocaleString("en-US") + " cft");
    if (inputs.constructionType !== "grey") {
      addQty("Tiles / Flooring (approx.)", Math.round(result.totalArea * 1.05).toLocaleString("en-US") + " sq ft (incl. ~5% wastage)");
    }

    resultsSection.hidden = false;
    if (!noScroll) resultsSection.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function labelForType(t) {
    if (t === "grey") return "Grey Structure";
    if (t === "turnkey") return "Turnkey";
    return "Complete House";
  }

  var lastResult = null, lastInputs = null;

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var inputs = readInputs();
    var err = validateInputs(inputs);
    if (err) {
      formError.textContent = err;
      formError.hidden = false;
      // v1.9.0: don't leave an old result on screen next to the error.
      resultsSection.hidden = true;
      lastResult = null; lastInputs = null;
      return;
    }
    formError.hidden = true;
    var result = computeEstimate(inputs);
    lastResult = result; lastInputs = inputs;
    renderResults(result, inputs);
    updateShare(result, inputs);
    resetFeedback();
    track("calculate", { construction_type: inputs.constructionType, quality: inputs.quality, city: inputs.city, covered_area: Math.round(inputs.coveredArea || 0), value: Math.round(result.grandTotal) });
  });

  // v1.12.0: once a result is on screen, it follows every input change
  // (no scrolling, no extra click). An invalid value shows the error instead.
  var liveTimer = null;
  function liveUpdate() {
    if (!lastResult) return;
    clearTimeout(liveTimer);
    liveTimer = setTimeout(function () {
      var inputs = readInputs();
      var err = validateInputs(inputs);
      if (err) { formError.textContent = err; formError.hidden = false; return; }
      formError.hidden = true;
      var result = computeEstimate(inputs);
      lastResult = result; lastInputs = inputs;
      renderResults(result, inputs, true);
      updateShare(result, inputs);
    }, 250);
  }
  form.addEventListener("input", liveUpdate);
  form.addEventListener("change", liveUpdate);

  if ($("reset-btn")) {
    $("reset-btn").addEventListener("click", function () {
      form.reset();
      applyDefaultType(); // keep the page's preset (e.g. Grey Structure) after reset
      coveredAreaTouched = false;
      wallLengthTouched = false;
      if (basementAreaWrap) basementAreaWrap.hidden = true;
      if (wallLengthWrap) wallLengthWrap.hidden = true;
      resultsSection.hidden = true;
      lastResult = null; lastInputs = null;
      formError.hidden = true;
      updatePlotHintAndSuggestion();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
  if ($("edit-inputs-btn")) {
    $("edit-inputs-btn").addEventListener("click", function () {
      $("calculator").scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  /* --------------------------------------------------------------------------
     v1.11.0 — shareable result link (#est:...) and WhatsApp share. The link
     carries only calculator inputs, in the URL hash, so nothing reaches the
     server and every shared link opens the same estimate.
  -------------------------------------------------------------------------- */
  var SHARE_RATE_KEYS = { cement: "rc", steel: "rs", bricksPer1000: "rb", sand: "rn", crush: "rk", approvalPerMarla: "ra" };
  function shareParams(inputs) {
    var p = new URLSearchParams();
    p.set("c", inputs.city); p.set("ps", inputs.plotSize); p.set("pu", inputs.plotUnit);
    if (inputs.marlaToSqft !== 225 && (inputs.plotUnit === "marla" || inputs.plotUnit === "kanal")) p.set("ms", inputs.marlaToSqft);
    p.set("ca", Math.round(inputs.coveredArea)); p.set("st", inputs.storeys);
    p.set("t", inputs.constructionType); p.set("q", inputs.quality);
    if (inputs.designComplexity !== "typical") p.set("d", inputs.designComplexity);
    p.set("r", inputs.bedrooms + "-" + inputs.bathrooms + "-" + inputs.kitchens);
    if (inputs.hasBasement) p.set("bs", Math.round(inputs.basementArea));
    if (inputs.hasBoundaryWall) p.set("bw", Math.round(inputs.wallLength));
    if (inputs.hasApprovalFee) p.set("ap", 1);
    Object.keys(SHARE_RATE_KEYS).forEach(function (k) {
      if (Math.abs(inputs.rates[k] - DEFAULT_RATES[k]) > 0.0001) p.set(SHARE_RATE_KEYS[k], inputs.rates[k]);
    });
    return p.toString();
  }
  function shareUrl(inputs) { return location.origin + location.pathname + "#est:" + shareParams(inputs); }
  function updateShare(result, inputs) {
    var wa = $("share-wa-btn");
    if (!wa) return;
    var url = shareUrl(inputs);
    var text = "My house construction estimate: " + trimNum(String(inputs.plotSize)) + " " + unitLabel(inputs.plotUnit) + ", "
      + (inputs.storeys === 1 ? "single storey" : inputs.storeys + " storeys") + ", " + Math.round(inputs.coveredArea).toLocaleString("en-US") + " sq ft, "
      + cityLabel(inputs.city) + ", " + labelForType(inputs.constructionType).toLowerCase() + " (" + inputs.quality + "): "
      + formatPKR(result.grandTotal) + " (" + toLacCroreLabel(result.grandTotal) + "). See the details or try your own: " + url;
    wa.href = "https://wa.me/?text=" + encodeURIComponent(text);
    try { history.replaceState(null, "", url); } catch (e) {}
  }
  if ($("share-wa-btn")) {
    $("share-wa-btn").addEventListener("click", function () { track("share", { method: "whatsapp" }); });
  }
  if ($("copy-link-btn")) {
    $("copy-link-btn").addEventListener("click", function () {
      if (!lastInputs) return;
      var url = shareUrl(lastInputs), note = $("share-note");
      function done(ok) {
        if (!note) return;
        note.textContent = ok ? "Link copied. Anyone who opens it sees this same estimate." : "Copy this link: " + url;
        note.hidden = false;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(function () { done(true); }, function () { done(false); });
      else done(false);
      track("share", { method: "copy_link" });
    });
  }
  function setVal(el, v) { if (el && v !== null && v !== undefined && v !== "") el.value = v; }
  function setRadio(name, v) {
    var r = v ? form.querySelector('input[name="' + name + '"][value="' + String(v).replace(/[^a-z-]/g, "") + '"]') : null;
    if (r) r.checked = true;
  }
  function loadSharedEstimate() {
    if (location.hash.indexOf("#est:") !== 0) return;
    var p;
    try { p = new URLSearchParams(location.hash.slice(5)); } catch (e) { return; }
    var city = p.get("c");
    if (city && $("city") && $("city").querySelector('option[value="' + city.replace(/[^a-z-]/g, "") + '"]')) $("city").value = city;
    if (["marla", "kanal", "sqft", "sqyd"].indexOf(p.get("pu")) >= 0) plotUnitSelect.value = p.get("pu");
    setVal(plotSizeInput, p.get("ps"));
    if (marlaStandardSelect && p.get("ms") && marlaStandardSelect.querySelector('option[value="' + parseFloat(p.get("ms")) + '"]')) marlaStandardSelect.value = String(parseFloat(p.get("ms")));
    if (p.get("st") && storeysSelect.querySelector('option[value="' + parseInt(p.get("st"), 10) + '"]')) storeysSelect.value = String(parseInt(p.get("st"), 10));
    coveredAreaTouched = false;
    updatePlotHintAndSuggestion();
    if (p.get("ca")) { setVal(coveredAreaInput, p.get("ca")); coveredAreaTouched = true; }
    setRadio("construction-type", p.get("t"));
    setRadio("quality", p.get("q"));
    setRadio("design-complexity", p.get("d") || "typical");
    var rooms = (p.get("r") || "").split("-");
    setVal($("bedrooms"), rooms[0]); setVal($("bathrooms"), rooms[1]); setVal($("kitchens"), rooms[2]);
    if (hasBasementCheckbox) {
      hasBasementCheckbox.checked = p.has("bs");
      if (basementAreaWrap) basementAreaWrap.hidden = !p.has("bs");
      setVal(basementAreaInput, p.get("bs"));
    }
    if (hasWallCheckbox) {
      hasWallCheckbox.checked = p.has("bw");
      if (wallLengthWrap) wallLengthWrap.hidden = !p.has("bw");
      if (p.has("bw")) { setVal(wallLengthInput, p.get("bw")); wallLengthTouched = true; }
    }
    if (hasApprovalCheckbox) hasApprovalCheckbox.checked = p.has("ap");
    var rateIds = { cement: "rate-cement", steel: "rate-steel", bricksPer1000: "rate-bricks", sand: "rate-sand", crush: "rate-crush", approvalPerMarla: "rate-approval" };
    Object.keys(SHARE_RATE_KEYS).forEach(function (k) { if (p.has(SHARE_RATE_KEYS[k])) setVal($(rateIds[k]), p.get(SHARE_RATE_KEYS[k])); });
    updateQuickAnswer();
    if (form.requestSubmit) form.requestSubmit(); else form.dispatchEvent(new Event("submit", { cancelable: true }));
    track("shared_estimate_opened", {});
  }

  /* --------------------------------------------------------------------------
     Example table: 5 Marla, 7 Marla, 10 Marla, 1 Kanal
  -------------------------------------------------------------------------- */
  function renderExamplesTable() {
    var wrap = $("examples-table-wrap");
    if (!wrap) return;
    var defaultRates = DEFAULT_RATES;
    var site = ((window.hccCalc && window.hccCalc.site) || "/").replace(/\/$/, "");
    var examples = [
      { label: "5 Marla", plotSqft: 5 * MARLA_TO_SQFT, storeys: 1, url: site + "/5-marla-house-construction-cost/" },
      { label: "7 Marla", plotSqft: 7 * MARLA_TO_SQFT, storeys: 1, url: site + "/7-marla-house-construction-cost/" },
      { label: "10 Marla", plotSqft: 10 * MARLA_TO_SQFT, storeys: 2, url: site + "/10-marla-house-construction-cost/" },
      { label: "1 Kanal", plotSqft: 1 * KANAL_TO_SQFT, storeys: 2, url: site + "/1-kanal-house-construction-cost/" }
    ];
    var rows = examples.map(function (ex) {
      var coveredArea = suggestCoveredArea(ex.plotSqft, ex.storeys);
      var r = computeEstimate({
        coveredArea: coveredArea, storeys: ex.storeys, hasBasement: false, basementArea: 0,
        hasBoundaryWall: false, wallLength: 0, hasApprovalFee: false, plotSqft: ex.plotSqft,
        constructionType: "complete", quality: "standard", designComplexity: "typical", city: "lahore",
        bedrooms: 3, bathrooms: 3, kitchens: 1, rates: defaultRates
      });
      return "<tr><td><a href=\"" + ex.url + "\">" + ex.label + "</a></td><td>" + Math.round(ex.plotSqft).toLocaleString("en-US") + " sq ft</td>" +
        "<td>" + ex.storeys + "</td><td>" + coveredArea.toLocaleString("en-US") + " sq ft</td>" +
        "<td>" + formatPKR(r.costPerSqft) + "</td><td>" + formatPKR(r.grandTotal) + "</td></tr>";
    }).join("");
    wrap.innerHTML =
      '<table class="mini-table"><thead><tr><th>Plot Size</th><th>Plot Area</th><th>Storeys</th>' +
      '<th>Est. Covered Area</th><th>Cost / Sq Ft</th><th>Est. Total Cost</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }
  renderExamplesTable();

  /* --------------------------------------------------------------------------
     PDF report — lazy-loaded jsPDF (kept OUT of the initial page weight so it
     never affects Largest Contentful Paint; only fetched the moment the
     visitor actually clicks "Download PDF Report").
  -------------------------------------------------------------------------- */
  // v1.8.0: self-hosted copy (theme assets/js/vendor) — CDN only as a fallback.
  var JSPDF_SRC = (window.hccCalc && window.hccCalc.jspdf) || "https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js";
  var jspdfLoadPromise = null;
  function loadJsPdf() {
    if (window.jspdf && window.jspdf.jsPDF) return Promise.resolve();
    if (jspdfLoadPromise) return jspdfLoadPromise;
    jspdfLoadPromise = new Promise(function (resolve, reject) {
      var s = document.createElement("script");
      s.src = JSPDF_SRC;
      s.async = true;
      s.onload = function () { resolve(); };
      s.onerror = function () { reject(new Error("load-failed")); };
      document.head.appendChild(s);
    });
    return jspdfLoadPromise;
  }

  function cityLabel(v) { var opt = document.querySelector('#city option[value="' + v + '"]'); return opt ? opt.textContent : v; }
  function unitLabel(v) { return v === "marla" ? "Marla" : v === "kanal" ? "Kanal" : v === "sqyd" ? "Sq Yd" : "Sq Ft"; }

  function buildAndDownloadPdf() {
    if (!lastResult || !lastInputs) return;
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: "mm", format: "a4" });
    var pageW = doc.internal.pageSize.getWidth();
    var pageH = doc.internal.pageSize.getHeight();
    var marginX = 16;
    var y = 18;

    function checkPageBreak(extra) { if (y + (extra || 8) > pageH - 16) { doc.addPage(); y = 18; } }
    function heading(text, size) {
      checkPageBreak(10);
      doc.setFont("helvetica", "bold"); doc.setFontSize(size || 13); doc.setTextColor(20, 30, 50);
      doc.text(text, marginX, y);
      y += size ? size * 0.5 + 2 : 8;
    }
    function bodyLine(label, value) {
      checkPageBreak(7);
      doc.setFont("helvetica", "normal"); doc.setFontSize(10); doc.setTextColor(60, 68, 80);
      doc.text(String(label), marginX, y);
      doc.setFont("helvetica", "bold"); doc.setTextColor(20, 30, 50);
      doc.text(String(value), pageW - marginX, y, { align: "right" });
      y += 6.2;
    }
    function divider() { checkPageBreak(4); doc.setDrawColor(220, 225, 235); doc.line(marginX, y, pageW - marginX, y); y += 5; }
    function tableRow(label, value, opts) {
      opts = opts || {};
      checkPageBreak(7.5);
      if (opts.shade) { doc.setFillColor(234, 241, 255); doc.rect(marginX - 2, y - 4.6, pageW - 2 * marginX + 4, 7, "F"); }
      doc.setFont("helvetica", opts.bold ? "bold" : "normal");
      doc.setFontSize(10);
      doc.setTextColor(30, 38, 50);
      doc.text(String(label), marginX, y);
      doc.text(String(value), pageW - marginX, y, { align: "right" });
      y += 7;
    }

    var now = new Date();
    var dateStr = now.toLocaleDateString("en-GB", { day: "2-digit", month: "long", year: "numeric", timeZone: "Asia/Karachi" });
    var timeStr = now.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: true, timeZone: "Asia/Karachi" });

    doc.setFont("helvetica", "bold"); doc.setFontSize(17); doc.setTextColor(13, 62, 161);
    doc.text("ConstructionCostPK — House Construction Cost Estimate", marginX, y); y += 7;
    doc.setFontSize(12); doc.setTextColor(20, 30, 50);
    doc.text("Construction Cost Estimate Report", marginX, y); y += 6;
    doc.setFont("helvetica", "normal"); doc.setFontSize(9.5); doc.setTextColor(90, 98, 110);
    doc.text("Generated on " + dateStr + " at " + timeStr + " (Pakistan Standard Time)", marginX, y); y += 8;
    divider();

    heading("Project Details", 12.5);
    bodyLine("City / Location:", cityLabel(lastInputs.city));
    bodyLine("Plot Size:", lastInputs.plotSize + " " + unitLabel(lastInputs.plotUnit) + "  (" + Math.round(sqftFromPlot(lastInputs.plotSize, lastInputs.plotUnit, lastInputs.marlaToSqft)).toLocaleString("en-US") + " sq ft" + ((lastInputs.plotUnit === "marla" || lastInputs.plotUnit === "kanal") && lastInputs.marlaToSqft !== MARLA_TO_SQFT_DEFAULT ? ", @ " + lastInputs.marlaToSqft + " sq ft/Marla" : "") + ")");
    bodyLine("Total Covered Area:", Math.round(lastResult.coveredArea).toLocaleString("en-US") + " sq ft");
    bodyLine("Number of Storeys:", String(lastInputs.storeys));
    bodyLine("Basement:", lastInputs.hasBasement ? (Math.round(lastInputs.basementArea).toLocaleString("en-US") + " sq ft") : "Not included");
    bodyLine("Boundary Wall:", lastInputs.hasBoundaryWall ? (Math.round(lastInputs.wallLength).toLocaleString("en-US") + " running ft") : "Not included");
    bodyLine("Construction Type:", labelForType(lastInputs.constructionType));
    bodyLine("Quality / Finishing Standard:", QUALITY[lastInputs.quality].label);
    bodyLine("Design Complexity:", (DESIGN_COMPLEXITY[lastInputs.designComplexity] || DESIGN_COMPLEXITY.typical).label);
    bodyLine("Bedrooms / Bathrooms / Kitchens:", lastInputs.bedrooms + " / " + lastInputs.bathrooms + " / " + lastInputs.kitchens);
    y += 2; divider();

    heading("Cost Summary", 12.5);
    tableRow("Total Estimated Construction Cost", formatPKR(lastResult.grandTotal), { bold: true, shade: true });
    tableRow("Cost Per Square Foot", formatPKR(lastResult.costPerSqft));
    tableRow("Grey Structure Cost", formatPKR(lastResult.greyStructureSubtotal));
    if (lastInputs.constructionType !== "grey") tableRow("Finishing Cost", formatPKR(lastResult.finishingSubtotal));
    if (lastInputs.constructionType === "turnkey") tableRow("Turnkey Fit-out Add-on", formatPKR(lastResult.turnkeyAddon));
    if (lastResult.boundaryWallCost > 0) tableRow("Boundary Wall", formatPKR(lastResult.boundaryWallCost));
    if (lastResult.approvalFeeCost > 0) tableRow("Approval / NOC Allowance (editable estimate)", formatPKR(lastResult.approvalFeeCost));
    tableRow("Estimated Material Cost", formatPKR(lastResult.materialCostTotal));
    tableRow("Estimated Labour & Services Cost", formatPKR(lastResult.labourCostTotal));
    y += 2; divider();

    heading("Itemized Cost Breakdown", 12.5);
    GREY_KEYS.forEach(function (k) {
      if (k === "basement" && lastResult.items.basement <= 0) return;
      tableRow(ITEM_LABELS[k], formatPKR(lastResult.items[k]));
    });
    tableRow("Grey Structure Subtotal", formatPKR(lastResult.greyStructureSubtotal), { bold: true });
    if (lastInputs.constructionType !== "grey") {
      FINISH_KEYS.forEach(function (k) {
        if (k === "roomExtras" && lastResult.items.roomExtras <= 0) return;
        tableRow(ITEM_LABELS[k], formatPKR(lastResult.items[k]));
      });
      tableRow("Finishing Subtotal", formatPKR(lastResult.finishingSubtotal), { bold: true });
    }
    if (lastInputs.constructionType === "turnkey") tableRow(ITEM_LABELS.turnkey, formatPKR(lastResult.items.turnkey));
    if (lastResult.items.boundaryWall > 0) tableRow(ITEM_LABELS.boundaryWall, formatPKR(lastResult.items.boundaryWall));
    if (lastResult.items.approvalFee > 0) tableRow(ITEM_LABELS.approvalFee, formatPKR(lastResult.items.approvalFee));
    tableRow("GRAND TOTAL (" + labelForType(lastInputs.constructionType) + ")", formatPKR(lastResult.grandTotal), { bold: true, shade: true });
    y += 2; divider();

    heading("Estimated Material Quantities", 12.5);
    tableRow("Cement", Math.round(lastResult.qty.cementBags).toLocaleString("en-US") + " bags (50kg)");
    tableRow("Steel / Saria", Math.round(lastResult.qty.steelKg).toLocaleString("en-US") + " kg (" + (lastResult.qty.steelKg / 1000).toFixed(2) + " tons)");
    tableRow("Bricks", Math.round(lastResult.qty.bricks).toLocaleString("en-US") + " nos.");
    tableRow("Sand", Math.round(lastResult.qty.sandCft).toLocaleString("en-US") + " cft");
    tableRow("Crush / Bajri", Math.round(lastResult.qty.crushCft).toLocaleString("en-US") + " cft");
    if (lastInputs.constructionType !== "grey") tableRow("Tiles / Flooring (approx.)", Math.round(lastResult.totalArea * 1.05).toLocaleString("en-US") + " sq ft");
    y += 4; divider();

    checkPageBreak(24);
    doc.setFont("helvetica", "italic"); doc.setFontSize(8.5); doc.setTextColor(120, 90, 10);
    var disclaimerText = "Disclaimer: This is an automated, planning-stage estimate based on editable, typical 2026 Pakistani market rate assumptions. Actual construction cost can vary by location, current market rates, site conditions, structural design, contractor and material brand. This report does not constitute a formal quotation and should not be used as the sole basis for a bank loan, tender or contract — please consult a licensed contractor or structural engineer.";
    var wrapped = doc.splitTextToSize(disclaimerText, pageW - 2 * marginX);
    doc.text(wrapped, marginX, y);
    y += wrapped.length * 4 + 4;

    doc.setFont("helvetica", "normal"); doc.setFontSize(8); doc.setTextColor(150, 155, 165);
    doc.text("Report generated by ConstructionCostPK (constructioncostpk.com) — free house construction cost calculator for Pakistan.", marginX, pageH - 10);

    var fileSafeDate = now.toISOString().slice(0, 10);
    doc.save("House-Construction-Cost-Estimate-" + fileSafeDate + ".pdf");
  }

  if ($("download-pdf-btn")) {
    $("download-pdf-btn").addEventListener("click", function () {
      var btn = $("download-pdf-btn");
      var originalLabel = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Preparing PDF…";
      loadJsPdf().then(function () {
        buildAndDownloadPdf();
        if (lastInputs) track("pdf_download", { construction_type: lastInputs.constructionType, city: lastInputs.city });
        btn.disabled = false;
        btn.textContent = originalLabel;
      }).catch(function () {
        btn.disabled = false;
        btn.textContent = originalLabel;
        alert("The PDF library could not be loaded (no internet connection?). Please try again once you're back online.");
      });
    });
  }

  /* --------------------------------------------------------------------------
     Floating "Calculate" shortcut button
  -------------------------------------------------------------------------- */
  var floatBtn = $("float-calc-btn");
  var calcCardEl = $("calculator");
  function updateFloatBtn() {
    if (!floatBtn || !calcCardEl) return;
    var cardBottom = calcCardEl.getBoundingClientRect().bottom;
    if (cardBottom < 100) floatBtn.classList.add("show");
    else floatBtn.classList.remove("show");
  }
  window.addEventListener("scroll", updateFloatBtn, { passive: true });
  window.addEventListener("resize", updateFloatBtn);
  if (floatBtn) {
    floatBtn.addEventListener("click", function () {
      calcCardEl.scrollIntoView({ behavior: "smooth", block: "start" });
      setTimeout(function () { var c = $("city"); if (c) c.focus(); }, 450);
    });
  }
  updateFloatBtn();

  /* --------------------------------------------------------------------------
     Pre-select construction type from the shortcode's default_type attribute
     (used by the dedicated Grey Structure Cost Calculator page) and, if
     requested, auto-run one calculation on load so a visitor arriving from a
     search result sees an instant number.
  -------------------------------------------------------------------------- */
  function applyDefaultType() {
    if (!form.dataset.defaultType) return;
    var preset = form.querySelector('input[name="construction-type"][value="' + form.dataset.defaultType + '"]');
    if (preset) preset.checked = true;
  }
  applyDefaultType();
  loadSharedEstimate();
  /* --------------------------------------------------------------------------
     v1.8.0 — analytics events (T7.2). Fires GA4 events only if gtag exists
     (i.e. the owner configured GA4); Clarity gets a custom tag. A DOM event
     is also dispatched so any other tag manager can listen for it.
     No calculator inputs identify a person; nothing is sent without gtag.
  -------------------------------------------------------------------------- */
  function track(name, params) {
    try {
      if (typeof window.gtag === "function") window.gtag("event", name, params || {});
      if (typeof window.clarity === "function") window.clarity("event", name);
      document.dispatchEvent(new CustomEvent("hcc:" + name, { detail: params || {} }));
    } catch (e) {}
  }

  /* --------------------------------------------------------------------------
     v1.8.0 — "Was this estimate close?" feedback (E6). Sends ONLY when the
     visitor clicks a feedback button, and only non-personal estimate data.
  -------------------------------------------------------------------------- */
  var fb = $("hcc-feedback");
  var fbRating = null;
  function resetFeedback() {
    if (!fb) return;
    fbRating = null;
    fb.querySelectorAll("[data-fb]").forEach(function (b) { b.classList.remove("is-selected"); b.disabled = false; });
    var a = fb.querySelector(".fb-actual"); if (a) a.hidden = true;
    var t = fb.querySelector(".fb-thanks"); if (t) t.hidden = true;
  }
  function sendFeedback(actual) {
    if (!fb || !fbRating || !lastResult || !lastInputs) return;
    var payload = {
      rating: fbRating, page: location.origin + location.pathname, city: lastInputs.city, type: lastInputs.constructionType,
      quality: lastInputs.quality, covered: Math.round(lastResult.coveredArea || 0), estimate: Math.round(lastResult.grandTotal || 0),
      actual: actual ? Math.round(actual) : 0
    };
    fb.querySelectorAll("[data-fb]").forEach(function (b) { b.disabled = true; });
    var thanks = fb.querySelector(".fb-thanks");
    fetch(fb.getAttribute("data-endpoint"), { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload), credentials: "omit" })
      .then(function (res) {
        if (!res.ok) throw new Error("http " + res.status);
        var a = fb.querySelector(".fb-actual"); if (a) a.hidden = true;
        if (thanks) { thanks.textContent = "Thank you — your feedback was recorded anonymously."; thanks.hidden = false; }
        track("estimate_feedback", { rating: fbRating });
      })
      .catch(function () {
        // v1.9.0: say so when it failed instead of thanking the visitor anyway.
        fb.querySelectorAll("[data-fb]").forEach(function (b) { b.disabled = false; });
        if (thanks) { thanks.textContent = "Sorry, your feedback could not be sent. Please try again later."; thanks.hidden = false; }
      });
  }
  if (fb) {
    fb.addEventListener("click", function (e) {
      var b = e.target.closest("[data-fb]");
      if (b) {
        fbRating = b.getAttribute("data-fb");
        fb.querySelectorAll("[data-fb]").forEach(function (x) { x.classList.toggle("is-selected", x === b); });
        if (fbRating === "lower" || fbRating === "higher") {
          var a = fb.querySelector(".fb-actual"); if (a) a.hidden = false;
          var inp = $("fb-actual-input"); if (inp) inp.focus();
        } else {
          sendFeedback(0);
        }
        return;
      }
      if (e.target.closest("[data-fb-send]")) {
        var v = parseFloat(($("fb-actual-input") || {}).value || "0");
        sendFeedback(isFinite(v) && v > 0 ? v : 0);
      }
    });
  }

  /* --------------------------------------------------------------------------
     v1.8.0 — Table of Contents links open the matching accordion (O3.2).
  -------------------------------------------------------------------------- */
  function openSectionFromHash(hash) {
    if (!hash || hash.indexOf("#sec-") !== 0) return;
    var d = document.querySelector(hash);
    if (d && d.tagName === "DETAILS") d.open = true;
  }
  document.querySelectorAll('.toc a[href^="#sec-"]').forEach(function (a) {
    a.addEventListener("click", function () { openSectionFromHash(a.getAttribute("href")); });
  });
  openSectionFromHash(location.hash);
})();
