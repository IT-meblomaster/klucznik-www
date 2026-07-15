'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const input =
        document.getElementById('dateRangeInput');

    const panel =
        document.getElementById('rangePicker');

    const grid =
        document.getElementById('rangePickerGrid');

    const title =
        document.getElementById('rangePickerTitle');

    const previousButton =
        document.getElementById('rangePickerPrevious');

    const nextButton =
        document.getElementById('rangePickerNext');

    const clearButton =
        document.getElementById('rangePickerClear');

    const closeButton =
        document.getElementById('rangePickerClose');

    const dateFromHidden =
        document.getElementById('dateFromHidden');

    const dateToHidden =
        document.getElementById('dateToHidden');

    if (
        !input
        || !panel
        || !grid
        || !title
        || !previousButton
        || !nextButton
        || !clearButton
        || !closeButton
        || !dateFromHidden
        || !dateToHidden
    ) {
        return;
    }

    let startDate = dateFromHidden.value
        ? new Date(`${dateFromHidden.value}T00:00:00`)
        : null;

    let endDate = dateToHidden.value
        ? new Date(`${dateToHidden.value}T00:00:00`)
        : null;

    let hoverDate = null;

    let selecting =
        startDate && !endDate
            ? 'end'
            : 'start';

    let viewDate = startDate
        ? new Date(startDate)
        : new Date();

    viewDate.setDate(1);

    const daysOfWeek = [
        'Pn',
        'Wt',
        'Śr',
        'Cz',
        'Pt',
        'So',
        'Nd',
    ];

    let animationFrame = 0;

    function formatDate(date) {
        const year = date.getFullYear();

        const month = String(
            date.getMonth() + 1
        ).padStart(2, '0');

        const day = String(
            date.getDate()
        ).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function stripTime(date) {
        const result = new Date(date);
        result.setHours(0, 0, 0, 0);

        return result;
    }

    function isSameDay(firstDate, secondDate) {
        return (
            firstDate
            && secondDate
            && firstDate.getFullYear() === secondDate.getFullYear()
            && firstDate.getMonth() === secondDate.getMonth()
            && firstDate.getDate() === secondDate.getDate()
        );
    }

    function normalizeRange(firstDate, secondDate) {
        if (
            !firstDate
            || !secondDate
        ) {
            return [
                firstDate,
                secondDate,
            ];
        }

        return firstDate <= secondDate
            ? [
                firstDate,
                secondDate,
            ]
            : [
                secondDate,
                firstDate,
            ];
    }

    function updateInput() {
        if (
            startDate
            && endDate
        ) {
            const [
                normalizedStart,
                normalizedEnd,
            ] = normalizeRange(
                startDate,
                endDate
            );

            input.value =
                `${formatDate(normalizedStart)} → ${formatDate(normalizedEnd)}`;

            dateFromHidden.value =
                formatDate(normalizedStart);

            dateToHidden.value =
                formatDate(normalizedEnd);

            selecting = 'start';

            return;
        }

        if (
            startDate
            && !endDate
        ) {
            input.value =
                `${formatDate(startDate)} → …`;

            dateFromHidden.value =
                formatDate(startDate);

            dateToHidden.value = '';
            selecting = 'end';

            return;
        }

        input.value = '';
        dateFromHidden.value = '';
        dateToHidden.value = '';
        selecting = 'start';
    }

    function scheduleRender() {
        if (animationFrame) {
            return;
        }

        animationFrame = requestAnimationFrame(() => {
            animationFrame = 0;
            render();
        });
    }

    function openPicker() {
        panel.style.display = 'block';

        if (startDate) {
            viewDate = new Date(startDate);
            viewDate.setDate(1);
        }

        render();
    }

    function closePicker() {
        panel.style.display = 'none';
        hoverDate = null;
    }

    function render() {
        const monthName = viewDate.toLocaleString(
            'pl-PL',
            {
                month: 'long',
            }
        );

        title.textContent =
            `${monthName.charAt(0).toUpperCase()}${monthName.slice(1)} ${viewDate.getFullYear()}`;

        grid.innerHTML = '';

        daysOfWeek.forEach((dayName) => {
            const element =
                document.createElement('div');

            element.className =
                'range-picker-day-of-week';

            element.textContent =
                dayName;

            grid.appendChild(element);
        });

        const firstDay =
            new Date(viewDate);

        const offset =
            (firstDay.getDay() + 6) % 7;

        const firstCellDate =
            new Date(firstDay);

        firstCellDate.setDate(
            firstDay.getDate() - offset
        );

        const [
            rangeStart,
            rangeEnd,
        ] = startDate && endDate
            ? normalizeRange(
                startDate,
                endDate
            )
            : (
                startDate && hoverDate
                    ? normalizeRange(
                        startDate,
                        hoverDate
                    )
                    : [
                        null,
                        null,
                    ]
            );

        for (
            let index = 0;
            index < 42;
            index++
        ) {
            const date =
                new Date(firstCellDate);

            date.setDate(
                firstCellDate.getDate() + index
            );

            const element =
                document.createElement('div');

            element.className =
                'range-picker-day';

            element.textContent =
                String(date.getDate());

            if (
                date.getMonth()
                !== viewDate.getMonth()
            ) {
                element.classList.add(
                    'is-outside-month'
                );
            }

            if (
                startDate
                && !endDate
                && hoverDate
                && rangeStart
                && rangeEnd
                && stripTime(date) >= stripTime(rangeStart)
                && stripTime(date) <= stripTime(rangeEnd)
            ) {
                element.classList.add(
                    'is-hover-range'
                );

                if (
                    isSameDay(
                        date,
                        startDate
                    )
                ) {
                    element.classList.remove(
                        'is-hover-range'
                    );
                }
            }

            if (
                startDate
                && endDate
                && rangeStart
                && rangeEnd
                && stripTime(date) >= stripTime(rangeStart)
                && stripTime(date) <= stripTime(rangeEnd)
            ) {
                element.classList.add(
                    'is-selected-range'
                );
            }

            if (
                startDate
                && isSameDay(
                    date,
                    startDate
                )
            ) {
                element.classList.add(
                    'is-start'
                );
            }

            if (
                endDate
                && isSameDay(
                    date,
                    endDate
                )
            ) {
                element.classList.add(
                    'is-end'
                );
            }

            element.addEventListener(
                'mouseenter',
                () => {
                    if (
                        startDate
                        && !endDate
                    ) {
                        hoverDate = date;
                        scheduleRender();
                    }
                }
            );

            element.addEventListener(
                'mousedown',
                (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (selecting === 'start') {
                        startDate = date;
                        endDate = null;
                        hoverDate = date;
                        selecting = 'end';

                        updateInput();
                        render();

                        return;
                    }

                    endDate = date;

                    const [
                        normalizedStart,
                        normalizedEnd,
                    ] = normalizeRange(
                        startDate,
                        endDate
                    );

                    startDate = normalizedStart;
                    endDate = normalizedEnd;
                    hoverDate = null;

                    updateInput();
                    render();
                    closePicker();
                }
            );

            grid.appendChild(element);
        }
    }

    previousButton.addEventListener(
        'mousedown',
        (event) => {
            event.preventDefault();
            event.stopPropagation();

            viewDate.setMonth(
                viewDate.getMonth() - 1
            );

            render();
        }
    );

    nextButton.addEventListener(
        'mousedown',
        (event) => {
            event.preventDefault();
            event.stopPropagation();

            viewDate.setMonth(
                viewDate.getMonth() + 1
            );

            render();
        }
    );

    clearButton.addEventListener(
        'mousedown',
        (event) => {
            event.preventDefault();
            event.stopPropagation();

            startDate = null;
            endDate = null;
            hoverDate = null;
            selecting = 'start';

            updateInput();
            render();
        }
    );

    closeButton.addEventListener(
        'mousedown',
        (event) => {
            event.preventDefault();
            event.stopPropagation();

            closePicker();
        }
    );

    input.addEventListener(
        'mousedown',
        (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (panel.style.display === 'none') {
                openPicker();
            } else {
                closePicker();
            }
        }
    );

    document.addEventListener(
        'mousedown',
        (event) => {
            if (panel.style.display === 'none') {
                return;
            }

            const clickedInside =
                panel.contains(event.target)
                || input.contains(event.target);

            if (!clickedInside) {
                closePicker();
            }
        }
    );

    if (
        startDate
        && endDate
    ) {
        const [
            normalizedStart,
            normalizedEnd,
        ] = normalizeRange(
            startDate,
            endDate
        );

        startDate = normalizedStart;
        endDate = normalizedEnd;
    }

    updateInput();
});