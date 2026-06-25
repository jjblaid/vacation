let calendar = null;
let calendarHolidays = [];
let familyDays = [];

// Calculate Family Day: Friday of the 3rd week (15th-21st of the month)
// If that day is a holiday, use the previous day instead
function calculateFamilyDays(viewStart, viewEnd) {
    familyDays = [];
    const start = new Date(viewStart);
    const end = new Date(viewEnd);
    
    const startYear = start.getFullYear();
    const endYear = end.getFullYear();
    const startMonth = start.getMonth();
    const endMonth = end.getMonth();
    
    for (let year = startYear; year <= endYear; year++) {
        const monthStart = year === startYear ? startMonth : 0;
        const monthEnd = year === endYear ? endMonth : 11;
        
        for (let month = monthStart; month <= monthEnd; month++) {
            for (let day = 15; day <= 21; day++) {
                const date = new Date(year, month, day);
                if (date.getDay() === 5) { // Friday
                    if (date >= start && date <= end) {
                        const y = date.getFullYear();
                        const m = String(date.getMonth() + 1).padStart(2, "0");
                        const d = String(date.getDate()).padStart(2, "0");
                        let dateStr = y + '-' + m + '-' + d;
                        
                        // Check if this date is a holiday
                        const isHoliday = calendarHolidays.some(h => h.date === dateStr);
                        if (isHoliday) {
                            // If it's a holiday, use the previous day
                            date.setDate(date.getDate() - 1);
                            const y2 = date.getFullYear();
                            const m2 = String(date.getMonth() + 1).padStart(2, "0");
                            const d2 = String(date.getDate()).padStart(2, "0");
                            dateStr = y2 + '-' + m2 + '-' + d2;
                        }
                        
                        familyDays.push(dateStr);
                    }
                    break;
                }
            }
        }
    }
}

function initCalendar(events = []) {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;
    
    if (calendar) {
        calendar.destroy();
    }
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        locale: 'ko',
        height: 'auto',
        events: events,
        eventClick: function(info) {
            showVacationDetail(info.event);
        },
        dateClick: function(info) {
            showDayVacations(info.dateStr);
        },
        eventDisplay: 'block',
        displayEventTime: false,
        dayMaxEvents: -1,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        },
        eventDidMount: function(info) {
            info.el.title = `${info.event.title} (${info.event.extendedProps.days}일)`;
        },
        datesSet: function(info) {
            const startDate = info.start.toISOString().split('T')[0];
            const endDate = info.end.toISOString().split('T')[0];
            loadCalendarEventsForRange(startDate, endDate);
            calculateFamilyDays(info.start, info.end);
            const calendarYear = info.view.getCurrentData().currentDate.getFullYear();
            api.holidays.list(calendarYear).then(res => {
                calendarHolidays = (res.data || []).map(h => ({date: h.date, name: h.name}));
                highlightHolidays();
            });
            highlightFamilyDays();
            const prevYear = document.getElementById('filterYear');
            if (prevYear && prevYear.value != calendarYear) {
                prevYear.value = calendarYear;
                loadVacationList(calendarYear, null, 1, null, null, false);
                renderDashboard(calendarYear);
            }
        },
        dayCellDidMount: function(info) {
            const dateStr = info.date.toISOString().split('T')[0];
            const holiday = calendarHolidays.find(h => h.date === dateStr);
            if (holiday) {
                info.el.classList.add('fc-day-holiday');
            }
            if (familyDays.includes(dateStr)) {
                info.el.classList.add('fc-day-family');
            }
        },
        views: {
            dayGridMonth: {
                dayMaxEvents: -1
            }
        }
    });
    
    calendar.render();
    
    // Initial calculation for Family Days
    const view = calendar.view;
    if (view) {
        calculateFamilyDays(view.activeStart, view.activeEnd);
        highlightFamilyDays();
    }
}

function highlightHolidays() {
    if (!calendar) return;
    
    // 기존 휴일 텍스트 및 클래스 제거
    document.querySelectorAll('.fc-day-holiday').forEach(el => {
        el.classList.remove('fc-day-holiday');
        const oldText = el.querySelector('.holiday-name');
        if (oldText) oldText.remove();
    });
    
    const dateCells = document.querySelectorAll('.fc-daygrid-day');
    dateCells.forEach(cell => {
        const dateStr = cell.getAttribute('data-date');
        if (!dateStr) return;
        
        const holiday = calendarHolidays.find(h => h.date === dateStr);
        if (!holiday) return;
        
        cell.classList.add('fc-day-holiday');
        
        // 상단(날짜 숫자 위)에 휴일 이름 추가
        const topDiv = cell.querySelector('.fc-daygrid-day-top');
        if (topDiv && !topDiv.querySelector('.holiday-name')) {
            const nameSpan = document.createElement('span');
            nameSpan.className = 'holiday-name';
            nameSpan.textContent = holiday.name;
            nameSpan.title = holiday.name;
            topDiv.insertBefore(nameSpan, topDiv.firstChild);
        }
    });
}

function highlightFamilyDays() {
    if (!calendar) return;
    
    // 기존 Family Day 텍스트 및 클래스 제거
    document.querySelectorAll('.fc-day-family').forEach(el => {
        el.classList.remove('fc-day-family');
        const oldText = el.querySelector('.family-day-name');
        if (oldText) oldText.remove();
    });
    
    const dateCells = document.querySelectorAll('.fc-daygrid-day');
    dateCells.forEach(cell => {
        const dateStr = cell.getAttribute('data-date');
        if (dateStr && familyDays.includes(dateStr)) {
            cell.classList.add('fc-day-family');
            
            // 왼쪽 상단에 Family Day 텍스트 추가
            const topDiv = cell.querySelector('.fc-daygrid-day-top');
            if (topDiv && !topDiv.querySelector('.family-day-name')) {
                const nameSpan = document.createElement('span');
                nameSpan.className = 'family-day-name';
                nameSpan.textContent = 'Family Day';
                nameSpan.title = 'Family Day (매월 3째주 금요일)';
                topDiv.insertBefore(nameSpan, topDiv.firstChild);
            }
        }
    });
}

function loadCalendarEventsForRange(start, end) {
    const startDate = start.split('T')[0];
    const endDate = end.split('T')[0];
    
    api.vacationRequests.calendar(startDate, endDate)
        .then(events => {
            if (calendar) {
                calendar.removeAllEvents();
                events.forEach(event => {
                    calendar.addEvent(event);
                });
            }
            highlightHolidays();
        })
        .catch(err => {
            console.error('Failed to load calendar:', err);
        });
}

function loadCalendarEvents() {
    const now = new Date();
    const start = calendar?.view?.activeStart?.toISOString().split('T')[0] || new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const end = calendar?.view?.activeEnd?.toISOString().split('T')[0] || new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    const year = calendar?.view?.activeStart?.getFullYear() || now.getFullYear();

    // Load holidays first, then calendar events
    api.holidays.list(year)
        .then(res => {
            calendarHolidays = (res.data || []).map(h => ({date: h.date, name: h.name}));
            // After holidays loaded, load calendar events
            return api.vacationRequests.calendar(start, end);
        })
        .then(events => {
            initCalendar(events);
            highlightHolidays();
            highlightFamilyDays();
        })
        .catch(err => {
            console.error('Failed to load calendar:', err);
            initCalendar([]);
        });
}

function formatEndDate(endStr) {
    if (!endStr) return null;
    const [y, m, d] = endStr.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() - 1);
    const yy = dt.getFullYear();
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    return `${yy}-${mm}-${dd}`;
}

function showVacationDetail(event) {
    const props = event.extendedProps;
    const detail = `
        <p><strong>사원:</strong> ${event.title}</p>
        <p><strong>휴가 유형:</strong> ${props.type}</p>
        <p><strong>기간:</strong> ${event.startStr} ~ ${formatEndDate(event.endStr)} (${props.days}일)</p>
        <p><strong>사유:</strong> ${props.reason || '-'}</p>
        <p><strong>상태:</strong> <span class="status-badge status-${props.status}">${props.status === 'applied' ? '신청' : '승인'}</span></p>
    `;
    
    document.getElementById('detailContent').innerHTML = detail;
    document.getElementById('vacationDetailModal').classList.remove('hidden');
}

function showDayVacations(dateStr) {
    if (!calendar) return;
    
    const events = calendar.getEvents();
    const dayEvents = events.filter(e => {
        const start = e.startStr?.split('T')[0];
        const end = e.endStr?.split('T')[0];
        if (!start) return false;
        const endDate = end || start;
        return dateStr >= start && dateStr < endDate;
    });
    
    if (dayEvents.length === 0) {
        alert('해당 날짜에 휴가 일정이 없습니다.');
        return;
    }
    
    let html = `<h3>${dateStr} 휴가 목록</h3><ul style="list-style:none; padding:0;">`;
    dayEvents.forEach(e => {
        const props = e.extendedProps;
        html += `<li style="padding:8px; border-bottom:1px solid #eee;">
            <strong>${e.title}</strong> - ${props.type} (${props.days}일)
            <span class="status-badge status-${props.status}">${props.status === 'applied' ? '신청' : '승인'}</span>
        </li>`;
    });
    html += '</ul>';
    
    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('vacationDetailModal').classList.remove('hidden');
}

window.calendar = calendar;
