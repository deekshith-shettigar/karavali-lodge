/* ==========================================
   Room Availability Calendar Module — v6
   Karavali Lodge
   ========================================== */
const CalendarModule = {
    currentMonth: new Date().getMonth(),
    currentYear:  new Date().getFullYear(),
    selectedDate: null,

    render() {
        const rooms    = DB.getAll(DB.ROOMS);
        const bookings = DB.getAll(DB.BOOKINGS).filter(b => b.status !== 'Cancelled' && b.status !== 'Completed');

        const monthNames = ['January','February','March','April','May','June',
                            'July','August','September','October','November','December'];
        const dayFull    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        const daysInMonth   = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        const firstDay      = new Date(this.currentYear, this.currentMonth, 1).getDay();
        const todayStr      = Utils.today();
        const prevMonthDays = new Date(this.currentYear, this.currentMonth, 0).getDate();

        const pad = n => String(n).padStart(2,'0');
        const monthStr  = pad(this.currentMonth + 1);
        const monthStart = `${this.currentYear}-${monthStr}-01`;
        const monthEnd   = `${this.currentYear}-${monthStr}-${pad(daysInMonth)}`;
        const monthBookings = bookings.filter(b => b.checkIn <= monthEnd && b.checkOut >= monthStart);

        const occupiedRooms  = rooms.filter(r => r.status === 'Occupied').length;
        const availableRooms = rooms.filter(r => (r.status||'Available') === 'Available').length;
        const reservedRooms  = rooms.filter(r => r.status === 'Reserved').length;
        const occupancyRate  = rooms.length ? Math.round((occupiedRooms / rooms.length) * 100) : 0;

        const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
        let cells = '';

        for (let i = 0; i < totalCells; i++) {
            let day, dateStr, isCurrentMonth = false, isToday = false;

            if (i < firstDay) {
                day = prevMonthDays - firstDay + i + 1;
                const pm = this.currentMonth === 0 ? 12 : this.currentMonth;
                const py = this.currentMonth === 0 ? this.currentYear - 1 : this.currentYear;
                dateStr = `${py}-${pad(pm)}-${pad(day)}`;
            } else if (i < firstDay + daysInMonth) {
                day = i - firstDay + 1;
                dateStr = `${this.currentYear}-${monthStr}-${pad(day)}`;
                isCurrentMonth = true;
                isToday = dateStr === todayStr;
            } else {
                day = i - firstDay - daysInMonth + 1;
                const nm = this.currentMonth === 11 ? 1 : this.currentMonth + 2;
                const ny = this.currentMonth === 11 ? this.currentYear + 1 : this.currentYear;
                dateStr = `${ny}-${pad(nm)}-${pad(day)}`;
            }

            const dayBookings = isCurrentMonth
                ? bookings.filter(b => dateStr >= b.checkIn && dateStr < b.checkOut)
                : [];
            const hasBooking  = dayBookings.length > 0;
            const fullyBooked = isCurrentMonth && rooms.length > 0 && dayBookings.length >= rooms.length;
            const isPast      = isCurrentMonth && dateStr < todayStr;
            const isSelected  = dateStr === this.selectedDate;
            const col         = i % 7;
            const isSun       = col === 0;
            const isSat       = col === 6;

            let state = !isCurrentMonth ? 'other'
                      : isSelected      ? 'selected'
                      : isToday         ? 'today'
                      : fullyBooked     ? 'full'
                      : hasBooking      ? 'booked'
                      : isPast          ? 'past'
                      :                   'free';

            // Occupancy fill percentage for bar
            let barHtml = '';
            if (isCurrentMonth && !isToday && !isSelected) {
                const pct = rooms.length > 0 ? Math.round((dayBookings.length / rooms.length) * 100) : 0;
                const barColor = pct === 0 ? 'transparent' : pct >= 100 ? '#e74c3c' : pct >= 60 ? '#e67e22' : '#3498db';
                barHtml = `<div class="cc-bar"><div class="cc-bar-fill" style="width:${pct}%;background:${barColor}"></div></div>`;
            }

            // Booking guest count badge
            let badgeHtml = '';
            if (isCurrentMonth && hasBooking) {
                const color = isToday || isSelected ? 'rgba(255,255,255,0.85)' : fullyBooked ? '#c0392b' : '#a04000';
                const bg    = isToday || isSelected ? 'rgba(255,255,255,0.18)' : fullyBooked ? 'rgba(231,76,60,0.15)' : 'rgba(230,126,34,0.15)';
                badgeHtml   = `<span class="cc-badge" style="color:${color};background:${bg}">${dayBookings.length}</span>`;
            }

            const clickAttr = isCurrentMonth
                ? `onclick="CalendarModule.selectDay('${dateStr}')" role="button" tabindex="0"` : '';

            cells += `<div class="cc-cell cc-${state}${(isSun||isSat) && isCurrentMonth && state==='free' ? ' cc-wknd' : ''}" ${clickAttr} data-date="${dateStr}">
                <span class="cc-num">${day}</span>
                ${barHtml}
                ${badgeHtml}
            </div>`;
        }

        const upcoming = bookings
            .filter(b => b.checkIn >= todayStr)
            .sort((a,b) => a.checkIn.localeCompare(b.checkIn))
            .slice(0, 5);

        return `
        <style>
            /* ══════════════════════════════════
               Calendar v6 — Karavali Lodge
            ══════════════════════════════════ */

            /* KPI strip */
            .cv6-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
            @media(max-width:680px){ .cv6-kpis{ grid-template-columns:repeat(2,1fr); } }
            .cv6-kpi { background:#fff; border-radius:14px; padding:14px 16px;
                        border:1px solid #ede8df; box-shadow:0 1px 8px rgba(59,26,10,0.05);
                        display:flex; align-items:center; gap:13px; transition:box-shadow 0.2s,transform 0.2s; }
            .cv6-kpi:hover { box-shadow:0 5px 18px rgba(59,26,10,0.10); transform:translateY(-1px); }
            .cv6-kpi-icon { width:42px; height:42px; border-radius:11px;
                             display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
            .cv6-kpi-val   { font-size:1.55rem; font-weight:800; line-height:1; }
            .cv6-kpi-lbl   { font-size:0.64rem; text-transform:uppercase; letter-spacing:1.1px; color:#b8a898; margin-top:3px; font-weight:600; }

            /* Page layout */
            .cv6-page { display:grid; grid-template-columns:1fr 288px; gap:18px; align-items:start; }
            @media(max-width:1080px){ .cv6-page{ grid-template-columns:1fr; } }

            /* ── Main calendar card ── */
            .cv6-card { background:#fff; border-radius:16px; border:1px solid #e8e0d4;
                         box-shadow:0 4px 24px rgba(59,26,10,0.09); overflow:hidden; }

            /* Header */
            .cv6-hdr { background:linear-gradient(135deg,#2A1007 0%,#4e200a 100%);
                        padding:0 20px; height:64px;
                        display:flex; align-items:center; justify-content:space-between; }
            .cv6-nav { width:34px; height:34px; border-radius:9px; flex-shrink:0;
                        border:1.5px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.07);
                        color:#fff; cursor:pointer; display:flex; align-items:center;
                        justify-content:center; font-size:0.9rem; transition:all 0.15s; }
            .cv6-nav:hover { background:rgba(196,148,58,0.4); border-color:#C4943A; }
            .cv6-month-name { font-family:'Georgia',serif; font-size:1.3rem; font-weight:700;
                               color:#fff; line-height:1; letter-spacing:0.3px; }
            .cv6-year { color:#C4943A; font-size:0.75rem; font-weight:700; margin-top:3px;
                         letter-spacing:2px; text-transform:uppercase; }
            .cv6-today-btn { padding:6px 16px; border-radius:8px; cursor:pointer;
                              border:1.5px solid rgba(196,148,58,0.55);
                              background:rgba(196,148,58,0.15); color:#D4AD5E;
                              font-size:0.7rem; font-weight:700; letter-spacing:1px;
                              transition:all 0.15s; }
            .cv6-today-btn:hover { background:rgba(196,148,58,0.4); color:#fff; border-color:#C4943A; }

            /* Mini stats row */
            .cv6-mini { display:grid; grid-template-columns:repeat(4,1fr);
                          background:linear-gradient(180deg,#faf6f0,#f5f0e8);
                          border-bottom:1px solid #ede5d8; }
            .cv6-mini-cell { padding:9px 6px; text-align:center; border-right:1px solid #ede5d8; }
            .cv6-mini-cell:last-child { border-right:none; }
            .cv6-mini-val { font-size:1.1rem; font-weight:800; line-height:1; font-family:'Georgia',serif; }
            .cv6-mini-lbl { font-size:0.55rem; color:#c0ae98; text-transform:uppercase; letter-spacing:0.9px; margin-top:2px; font-weight:600; }

            /* Day name header row */
            .cv6-dnames { display:grid; grid-template-columns:repeat(7,1fr);
                           border-bottom:1px solid #f0ebe2; }
            .cv6-dname { padding:9px 0 7px; text-align:center;
                          font-size:0.7rem; font-weight:700;
                          text-transform:uppercase; letter-spacing:0.8px; }

            /* Calendar grid */
            .cv6-grid { display:grid; grid-template-columns:repeat(7,1fr);
                         gap:0; padding:0; }

            /* ── Cells ── */
            .cc-cell { position:relative; padding:8px 6px 6px;
                        display:flex; flex-direction:column; align-items:center;
                        min-height:52px; border-right:1px solid #f5f1eb;
                        border-bottom:1px solid #f5f1eb;
                        cursor:default; transition:background 0.13s; }
            /* Remove right border on last col, bottom border on last row */
            .cc-cell:nth-child(7n) { border-right:none; }

            .cc-cell[role="button"] { cursor:pointer; }

            /* Default hover — plain days */
            .cc-cell[role="button"]:hover { background:#f0ebe2; }

            /* Today hover — stays dark, just slightly lighter */
            .cc-cell.cc-today:hover { background:linear-gradient(160deg,#4a2210,#6e3a1e) !important; }

            /* Selected hover */
            .cc-cell.cc-selected:hover { background:linear-gradient(160deg,#1a5276,#2176ae) !important; }

            /* Fully booked hover */
            .cc-cell.cc-full:hover { background:#fbd0d0 !important; }

            /* Has booking hover */
            .cc-cell.cc-booked:hover { background:#fdf0e0 !important; }

            /* Past day hover — subtle */
            .cc-cell.cc-past:hover { background:#f4f3f0 !important; }

            /* Weekend hover */
            .cc-cell.cc-wknd:hover { background:#f7f4ee !important; }

            .cc-cell[role="button"]:focus-visible { outline:2px solid #C4943A; outline-offset:-2px; z-index:2; }

            .cc-num { font-size:0.85rem; font-weight:700; line-height:1; z-index:1; }

            /* Occupancy bar — very subtle strip at top of cell */
            .cc-bar { position:absolute; top:0; left:0; right:0; height:3px;
                       background:#f0ebe2; border-radius:0; overflow:hidden; }
            .cc-bar-fill { height:100%; transition:width 0.4s ease; border-radius:0; }

            /* Booking count badge */
            .cc-badge { margin-top:5px; font-size:0.58rem; font-weight:800;
                         padding:2px 6px; border-radius:10px; line-height:1.4;
                         letter-spacing:0.3px; z-index:1; }

            /* ── Cell states ── */
            .cc-other { opacity:0.3; }
            .cc-other .cc-num { color:#c0b0a0; font-weight:400; font-size:0.78rem; }

            .cc-today { background:linear-gradient(160deg,#3B1A0A,#5C2E15) !important;
                         border-right-color:rgba(196,148,58,0.4) !important;
                         border-bottom-color:rgba(196,148,58,0.4) !important; }
            .cc-today .cc-num { color:#fff; font-weight:800; font-size:0.9rem; }
            .cc-today .cc-bar { background:rgba(255,255,255,0.15); }

            .cc-selected { background:linear-gradient(160deg,#154360,#1a6496) !important;
                            border-right-color:rgba(41,128,185,0.5) !important;
                            border-bottom-color:rgba(41,128,185,0.5) !important; }
            .cc-selected .cc-num { color:#fff; font-weight:800; }
            .cc-selected .cc-bar { background:rgba(255,255,255,0.15); }

            .cc-full { background:#fde8e8; }
            .cc-full .cc-num { color:#c0392b; font-weight:700; }

            .cc-booked { background:#fff9f2; }
            .cc-booked .cc-num { color:#a04000; font-weight:700; }

            .cc-free { background:#fff; }
            .cc-free .cc-num { color:#2A1007; }

            .cc-past { background:#fafaf8; }
            .cc-past .cc-num { color:#c8bfb5; }

            .cc-wknd { background:#fdfcfa; }
            .cc-wknd .cc-num { color:#7a5030; }

            /* Legend */
            .cv6-legend { display:flex; align-items:center; gap:12px; flex-wrap:wrap;
                           padding:9px 16px 11px; border-top:1px solid #f0ebe2;
                           background:linear-gradient(180deg,#fdfaf6,#faf6f0); }
            .cv6-leg { display:flex; align-items:center; gap:5px; font-size:0.68rem; color:#999; font-weight:500; }
            .cv6-leg-sw { width:11px; height:11px; border-radius:3px; border:1.5px solid transparent; flex-shrink:0; }

            /* Detail panel */
            .cv6-detail { background:#fff; border-radius:14px; border:1px solid #e8e0d4;
                           box-shadow:0 5px 22px rgba(59,26,10,0.10); overflow:hidden;
                           margin-top:14px; animation:cv6Slide 0.2s ease; }
            @keyframes cv6Slide { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
            .cv6-detail-hdr { background:linear-gradient(135deg,#2A1007,#4e200a);
                               padding:11px 16px; display:flex; align-items:center; justify-content:space-between; }
            .cv6-detail-close { width:26px; height:26px; border-radius:7px; border:none; flex-shrink:0;
                                  background:rgba(255,255,255,0.1); color:rgba(255,255,255,0.7);
                                  cursor:pointer; display:flex; align-items:center;
                                  justify-content:center; font-size:0.75rem; transition:all 0.15s; }
            .cv6-detail-close:hover { background:rgba(255,255,255,0.22); color:#fff; }

            /* Sidebar cards */
            .cv6-side { background:#fff; border-radius:14px; border:1px solid #e8e0d4;
                         box-shadow:0 2px 12px rgba(59,26,10,0.06); overflow:hidden; margin-bottom:12px; }
            .cv6-side-hdr { background:linear-gradient(135deg,#2A1007,#4e200a);
                             padding:10px 14px; display:flex; align-items:center; gap:8px; }
            .cv6-side-hdr span { color:#fff; font-weight:600; font-size:0.82rem; }
            .cv6-side-hdr i { color:#C4943A; font-size:0.85rem; }

            /* Avail bars */
            .avb { margin-bottom:10px; }
            .avb:last-child { margin-bottom:0; }
            .avb-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
            .avb-name { font-size:0.76rem; font-weight:600; color:#3B1A0A; display:flex; align-items:center; gap:6px; }
            .avb-dot  { width:8px; height:8px; border-radius:2px; flex-shrink:0; }
            .avb-cnt  { font-size:0.75rem; font-weight:700; }
            .avb-trk  { background:#f2ede6; border-radius:4px; height:5px; overflow:hidden; }
            .avb-fill { height:100%; border-radius:4px; transition:width 0.5s ease; }

            /* Room chips */
            .cv6-room-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(70px,1fr)); gap:7px; }
            .cv6-chip { border-radius:10px; padding:8px 5px; text-align:center;
                         border:1.5px solid transparent; transition:all 0.15s; }
            .cv6-chip:hover { transform:translateY(-2px); box-shadow:0 3px 9px rgba(0,0,0,0.09); }
            .cv6-chip.available   { background:rgba(40,167,69,0.08);  border-color:rgba(40,167,69,0.22); }
            .cv6-chip.occupied    { background:rgba(220,53,69,0.08);  border-color:rgba(220,53,69,0.22); }
            .cv6-chip.reserved    { background:rgba(255,193,7,0.10);  border-color:rgba(255,193,7,0.35); }
            .cv6-chip.cleaning    { background:rgba(23,162,184,0.08); border-color:rgba(23,162,184,0.22); }
            .cv6-chip.maintenance { background:rgba(108,117,125,0.08);border-color:rgba(108,117,125,0.22); }
            .cv6-chip-num  { font-size:0.9rem; font-weight:800; color:#2A1007; line-height:1; }
            .cv6-chip-type { font-size:0.56rem; color:#bbb; text-transform:uppercase; letter-spacing:0.4px; margin-top:1px; }
            .cv6-chip .badge { font-size:0.52rem; padding:2px 5px; margin-top:4px; display:inline-block; }

            /* Arrivals */
            .cv6-arr { display:flex; align-items:center; gap:9px; padding:8px 12px; border-bottom:1px solid #f5f0eb; }
            .cv6-arr:last-child { border-bottom:none; }
            .cv6-arr-day { width:30px; height:30px; border-radius:8px; background:rgba(196,148,58,0.12);
                            color:#C4943A; font-size:0.8rem; font-weight:800;
                            display:flex; align-items:center; justify-content:center; flex-shrink:0; }
            .cv6-arr-name { font-size:0.78rem; font-weight:700; color:#2A1007;
                             white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .cv6-arr-sub  { font-size:0.65rem; color:#bbb; }
        </style>

        <!-- KPI Row -->
        <div class="cv6-kpis">
            <div class="cv6-kpi">
                <div class="cv6-kpi-icon" style="background:rgba(196,148,58,0.12);color:#C4943A"><i class="bi bi-calendar-check"></i></div>
                <div><div class="cv6-kpi-val" style="color:#2A1007">${monthBookings.length}</div><div class="cv6-kpi-lbl">This Month</div></div>
            </div>
            <div class="cv6-kpi">
                <div class="cv6-kpi-icon" style="background:rgba(40,167,69,0.10);color:#27ae60"><i class="bi bi-door-open"></i></div>
                <div><div class="cv6-kpi-val" style="color:#27ae60">${availableRooms}</div><div class="cv6-kpi-lbl">Available</div></div>
            </div>
            <div class="cv6-kpi">
                <div class="cv6-kpi-icon" style="background:rgba(220,53,69,0.10);color:#e74c3c"><i class="bi bi-person-fill"></i></div>
                <div><div class="cv6-kpi-val" style="color:#e74c3c">${occupiedRooms}</div><div class="cv6-kpi-lbl">Occupied</div></div>
            </div>
            <div class="cv6-kpi">
                <div class="cv6-kpi-icon" style="background:rgba(111,66,193,0.10);color:#6f42c1"><i class="bi bi-graph-up-arrow"></i></div>
                <div><div class="cv6-kpi-val" style="color:#6f42c1">${occupancyRate}%</div><div class="cv6-kpi-lbl">Occupancy</div></div>
            </div>
        </div>

        <!-- Main layout -->
        <div class="cv6-page">

            <!-- Calendar -->
            <div>
                <div class="cv6-card">

                    <!-- Header -->
                    <div class="cv6-hdr">
                        <button class="cv6-nav" onclick="CalendarModule.prevMonth()"><i class="bi bi-chevron-left"></i></button>
                        <div style="text-align:center">
                            <div class="cv6-month-name">${monthNames[this.currentMonth]}</div>
                            <div class="cv6-year">${this.currentYear}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <button class="cv6-today-btn" onclick="CalendarModule.goToToday()">Today</button>
                            <button class="cv6-nav" onclick="CalendarModule.nextMonth()"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>

                    <!-- Mini stats -->
                    <div class="cv6-mini">
                        ${[
                            {val:daysInMonth,           lbl:'Days',     color:'#C4943A'},
                            {val:monthBookings.length,  lbl:'Bookings', color:'#e74c3c'},
                            {val:availableRooms,        lbl:'Free',     color:'#27ae60'},
                            {val:reservedRooms,         lbl:'Reserved', color:'#8e44ad'},
                        ].map(s=>`<div class="cv6-mini-cell">
                            <div class="cv6-mini-val" style="color:${s.color}">${s.val}</div>
                            <div class="cv6-mini-lbl">${s.lbl}</div>
                        </div>`).join('')}
                    </div>

                    <!-- Day names — full 3-letter -->
                    <div class="cv6-dnames">
                        ${dayFull.map((d,i)=>`
                            <div class="cv6-dname" style="color:${i===0?'#c0392b':i===6?'#2980b9':'#b0a090'}">
                                ${d}
                            </div>`).join('')}
                    </div>

                    <!-- Grid -->
                    <div class="cv6-grid">${cells}</div>

                    <!-- Legend -->
                    <div class="cv6-legend">
                        <span style="font-size:0.6rem;color:#ccc;font-weight:700;text-transform:uppercase;letter-spacing:1px">Legend</span>
                        ${[
                            {bg:'linear-gradient(135deg,#3B1A0A,#5C2E15)', bd:'#C4943A',             lbl:'Today'},
                            {bg:'linear-gradient(135deg,#154360,#1a6496)', bd:'#1a6496',             lbl:'Selected'},
                            {bg:'#fff9f2',                                  bd:'rgba(230,126,34,.3)', lbl:'Has Booking'},
                            {bg:'#fde8e8',                                  bd:'rgba(231,76,60,.4)',  lbl:'Fully Booked'},
                            {bg:'#fff',                                     bd:'#e8e0d4',             lbl:'Available'},
                        ].map(l=>`<div class="cv6-leg">
                            <div class="cv6-leg-sw" style="background:${l.bg};border-color:${l.bd}"></div>${l.lbl}
                        </div>`).join('')}
                        <div class="cv6-leg" style="margin-left:auto">
                            <div style="height:4px;width:28px;border-radius:2px;background:linear-gradient(90deg,#3498db,#e67e22,#e74c3c)"></div>
                            Occupancy
                        </div>
                    </div>
                </div>

                <!-- Day detail panel -->
                <div class="cv6-detail" id="dayDetailCard" style="display:none">
                    <div class="cv6-detail-hdr">
                        <div style="display:flex;align-items:center;gap:9px">
                            <i class="bi bi-calendar-event" style="color:#C4943A;font-size:0.9rem"></i>
                            <span style="color:#fff;font-weight:600;font-size:0.86rem">
                                Bookings — <span id="dayDetailDate" style="color:#D4AD5E"></span>
                            </span>
                        </div>
                        <button class="cv6-detail-close" onclick="CalendarModule.closeDetail()"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div id="dayDetailContent"></div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>

                <!-- Availability bars -->
                <div class="cv6-side">
                    <div class="cv6-side-hdr"><i class="bi bi-pie-chart-fill"></i><span>Availability</span></div>
                    <div style="padding:13px 15px">${this.renderAvailabilityBars(rooms)}</div>
                </div>

                <!-- Room chips -->
                <div class="cv6-side">
                    <div class="cv6-side-hdr"><i class="bi bi-grid-3x3-gap-fill"></i><span>Room Status Today</span></div>
                    <div style="padding:11px">
                        ${rooms.length === 0
                            ? '<div class="empty-state"><i class="bi bi-door-closed d-block"></i>No rooms</div>'
                            : `<div class="cv6-room-grid">${rooms.map(r=>`
                                <div class="cv6-chip ${(r.status||'Available').toLowerCase()}">
                                    <div class="cv6-chip-num">${Utils.escapeHtml(r.roomNumber)}</div>
                                    <div class="cv6-chip-type">${Utils.escapeHtml(r.roomType)}</div>
                                    ${Utils.statusBadge(r.status||'Available')}
                                </div>`).join('')}</div>`}
                    </div>
                </div>

                <!-- Upcoming arrivals -->
                ${upcoming.length > 0 ? `
                <div class="cv6-side">
                    <div class="cv6-side-hdr"><i class="bi bi-arrow-right-circle-fill"></i><span>Upcoming Arrivals</span></div>
                    <div style="padding:3px 0">
                        ${upcoming.map(b=>{
                            const room = DB.getById(DB.ROOMS, b.roomId);
                            return `<div class="cv6-arr">
                                <div class="cv6-arr-day">${new Date(b.checkIn).getDate()}</div>
                                <div style="flex:1;min-width:0">
                                    <div class="cv6-arr-name">${Utils.escapeHtml(b.guestName)}</div>
                                    <div class="cv6-arr-sub">Rm ${room?Utils.escapeHtml(room.roomNumber):'?'} · ${Utils.formatDate(b.checkIn)}</div>
                                </div>
                                ${Utils.statusBadge(b.status)}
                            </div>`;
                        }).join('')}
                    </div>
                </div>` : ''}

            </div>
        </div>`;
    },

    renderAvailabilityBars(rooms) {
        const total = rooms.length || 1;
        const statuses = {};
        rooms.forEach(r => { const s = r.status||'Available'; statuses[s]=(statuses[s]||0)+1; });
        const colors = { Available:'#27ae60', Occupied:'#e74c3c', Reserved:'#f39c12', Cleaning:'#17a2b8', Maintenance:'#6c757d' };
        return ['Available','Occupied','Reserved','Cleaning','Maintenance'].map(s => {
            const count = statuses[s]||0;
            const pct   = (count/total*100).toFixed(1);
            return `<div class="avb">
                <div class="avb-top">
                    <span class="avb-name"><span class="avb-dot" style="background:${colors[s]}"></span>${s}</span>
                    <span class="avb-cnt" style="color:${colors[s]}">${count}<span style="color:#ddd;font-weight:400"> /${rooms.length}</span></span>
                </div>
                <div class="avb-trk"><div class="avb-fill" style="width:${pct}%;background:${colors[s]}"></div></div>
            </div>`;
        }).join('');
    },

    selectDay(dateStr) {
        if (this.selectedDate === dateStr) { this.closeDetail(); return; }
        this.selectedDate = dateStr;
        document.querySelectorAll('.cc-cell[role="button"]').forEach(el => {
            const d = el.getAttribute('data-date');
            if (d === dateStr) {
                el.className = el.className.replace(/\bcc-(free|booked|full|past|wknd|today)\b/g,'').trim();
                el.classList.add('cc-selected');
            } else if (el.classList.contains('cc-selected')) {
                el.classList.remove('cc-selected');
                el.classList.add('cc-free');
            }
        });
        this.showDayDetails(dateStr);
    },

    closeDetail() {
        this.selectedDate = null;
        document.getElementById('dayDetailCard').style.display = 'none';
        document.querySelectorAll('.cc-selected').forEach(el => {
            el.classList.remove('cc-selected'); el.classList.add('cc-free');
        });
    },

    goToToday() {
        this.currentMonth = new Date().getMonth();
        this.currentYear  = new Date().getFullYear();
        this.selectedDate = null;
        LodgeApp.navigate('calendar');
    },
    prevMonth() {
        if (--this.currentMonth < 0) { this.currentMonth=11; this.currentYear--; }
        this.selectedDate = null;
        LodgeApp.navigate('calendar');
    },
    nextMonth() {
        if (++this.currentMonth > 11) { this.currentMonth=0; this.currentYear++; }
        this.selectedDate = null;
        LodgeApp.navigate('calendar');
    },

    showDayDetails(dateStr) {
        const bookings = DB.getAll(DB.BOOKINGS).filter(b =>
            b.status !== 'Cancelled' && b.status !== 'Completed' &&
            dateStr >= b.checkIn && dateStr < b.checkOut
        );
        const rooms     = DB.getAll(DB.ROOMS);
        const bookedIds = new Set(bookings.map(b => b.roomId));
        const freeRooms = rooms.filter(r => !bookedIds.has(r.id));

        document.getElementById('dayDetailDate').textContent = Utils.formatDate(dateStr);

        let html = '';
        if (bookings.length === 0) {
            html = `<div style="text-align:center;padding:28px 20px">
                <i class="bi bi-calendar-x" style="font-size:1.9rem;color:#e0d8ce;display:block;margin-bottom:10px"></i>
                <p style="color:#bbb;margin:0;font-size:0.83rem">No bookings on this date</p>
                ${freeRooms.length>0?`<p style="color:#27ae60;font-size:0.76rem;margin-top:5px;font-weight:600"><i class="bi bi-check-circle me-1"></i>${freeRooms.length} room(s) available</p>`:''}
            </div>`;
        } else {
            html = `<div style="display:flex;gap:7px;padding:10px 14px 2px;flex-wrap:wrap">
                <span style="background:#fde8e8;color:#c0392b;border-radius:20px;padding:3px 10px;font-size:0.7rem;font-weight:700">
                    <i class="bi bi-person-fill me-1"></i>${bookings.length} Booked
                </span>
                <span style="background:#e8f8ee;color:#1e8449;border-radius:20px;padding:3px 10px;font-size:0.7rem;font-weight:700">
                    <i class="bi bi-door-open me-1"></i>${freeRooms.length} Free
                </span>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0" style="font-size:0.8rem">
                    <thead><tr><th style="padding-left:14px">Guest</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Status</th></tr></thead>
                    <tbody>
                    ${bookings.map(b=>{
                        const room = DB.getById(DB.ROOMS, b.roomId);
                        return `<tr>
                            <td style="padding-left:14px"><strong>${Utils.escapeHtml(b.guestName)}</strong>${b.mobile?`<br><small class="text-muted">${Utils.escapeHtml(b.mobile)}</small>`:''}</td>
                            <td>${room?`<strong>${Utils.escapeHtml(room.roomNumber)}</strong><br><small class="text-muted">${Utils.escapeHtml(room.roomType)}</small>`:'-'}</td>
                            <td>${Utils.formatDate(b.checkIn)}</td>
                            <td>${Utils.formatDate(b.checkOut)}</td>
                            <td>${Utils.statusBadge(b.status)}</td>
                        </tr>`;
                    }).join('')}
                    </tbody>
                </table>
            </div>`;
            if (freeRooms.length>0 && freeRooms.length<=12) {
                html += `<div style="padding:9px 14px 12px;border-top:1px solid #f5f0eb;background:#fdfaf6">
                    <div style="font-size:0.62rem;color:#c0ae98;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">
                        <i class="bi bi-door-open me-1" style="color:#27ae60"></i>Available Rooms
                    </div>
                    <div style="display:flex;gap:5px;flex-wrap:wrap">
                        ${freeRooms.map(r=>`<span style="background:rgba(39,174,96,0.1);color:#1e8449;border:1px solid rgba(39,174,96,0.25);border-radius:6px;padding:2px 9px;font-size:0.7rem;font-weight:700">${Utils.escapeHtml(r.roomNumber)}</span>`).join('')}
                    </div>
                </div>`;
            }
        }

        document.getElementById('dayDetailContent').innerHTML = html;
        const card = document.getElementById('dayDetailCard');
        card.style.display = 'block';
        card.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }
};