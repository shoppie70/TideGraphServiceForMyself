const get_dataset = function (parent_data, type) {
    let array = [];
    for (let i = 0; i < parent_data.length; i++) {
        array.push(parent_data[i][type])
    }

    return array;
}

const draw_chart = (label, data) => {
    const ctx = document.getElementById("chart");

    const myLineChart = new Chart(ctx, {
        type: "line",
        data: {
            "labels": label,
            "datasets": [{
                "label": "潮位",
                "data": data,
                "fill": true,
                "borderColor": "#35b0eb",
                "backgroundColor":"#a9e3ff",
                "lineTension": 0.5,
                "borderWidth": 7,
                "pointHoverBorderWidth": 10,
            }]
        },
        options: {
            title: {},
            legend: {},
            scales: {},
            maintainAspectRatio: false
        },
    });
}