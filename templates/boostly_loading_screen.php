<style>
    .boostly-loader {
        border: 8px solid #f3f3f3; /* Light grey background for the loader */
        border-top: 8px solid #3498db; /* Blue color for the spinning part */
        border-radius: 50%; /* Makes the loader circular */
        width: 60px; /* Size of the loader */
        height: 60px; /* Size of the loader */
        animation: spin 2s linear infinite; /* Animation applied to the loader */
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }    
</style>
<div id="loadingScreen" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.7); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex">
        <div class="grid-container" style="display:grid; place-items: center;">
            <img src="https://boostly.co.uk/wp-content/uploads/2022/04/Boostly-Alt-Logo-RGB-Transparent-Background-1024x240.png" alt="" style="width:300px">
            <div id="boostly-loading-container-text"></div>
            <div class="boostly-loader"></div>
            <div id="boostly-substatus"></div>
        </div>
    </div>
</div>