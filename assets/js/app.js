const get_dataset = function (parent_data, type) {
    let array = [];
    for (let i = 0; i < parent_data.length; i++) {
        array.push(parent_data[i][type])
    }

    return array;
}

const draw_chart = (label, data) => {
    const ctx = document.getElementById("chart");

    const adjustStyles = (chart, width) => {
        if (width < 768) { // スマホなどの幅が狭い場合
            chart.data.datasets[0].borderWidth = 2; // 線を細く
            chart.data.datasets[0].pointHoverBorderWidth = 3; // 点も小さく
        } else { // 画面が広い場合
            chart.data.datasets[0].borderWidth = 7;
            chart.data.datasets[0].pointHoverBorderWidth = 10;
        }
    }

    const myLineChart = new Chart(ctx, {
        type: "line",
        data: {
            "labels": label,
            "datasets": [{
                "label": "潮位",
                "data": data,
                "fill": true,
                "borderColor": "#35b0eb",
                "backgroundColor": "#a9e3ff",
                "lineTension": 0.5,
                "borderWidth": 7,
                "pointHoverBorderWidth": 10,
            }]
        },
        options: {
            responsive: true, // レスポンシブ対応
            maintainAspectRatio: false,
            onResize: (chart, size) => {
                adjustStyles(chart, size.width); // リサイズ時にスタイルを適用
                chart.update();
            },
            title: {},
            legend: {},
            scales: {}
        },
    });

    // 初回読み込み時の幅に応じてスタイルを調整
    adjustStyles(myLineChart, window.innerWidth);
    myLineChart.update(); // 初回描画時に反映
}

