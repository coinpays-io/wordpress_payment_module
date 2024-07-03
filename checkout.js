const settings = window.wc.wcSettings.getSetting( 'coinpays_payment_gateway_data', {} );

const coinpays_payment_gateway = settings.coinpays_payment_gateway;

const ContentIframe = () => {
    return window.wp.htmlEntities.decodeEntities( coinpays_payment_gateway.description);
};

const LabelComponentIframe = () => {
    return window.wp.element.createElement(
        "div",
        {
            style: {
                display: "flex",
                alignItems: "center",
                gap: "5px",
            },
        },
        window.wp.element.createElement("img", {
            src: coinpays_payment_gateway.icon,
            alt: `${coinpays_payment_gateway.title}`,
            style: {
                width: "100px",
                marginRight: "10px",
                maxHeight: "20px",
                objectFit: "contain",
                display: coinpays_payment_gateway.icon ? "block" : "none"
            },
        }),
        window.wp.element.createElement(
            "span", null, coinpays_payment_gateway.title
        )
    )
}

const Block_Gateway_Iframe = {
    name: 'coinpays_payment_gateway',
    label: window.wp.element.createElement(LabelComponentIframe, null),
    content: Object( window.wp.element.createElement )( ContentIframe, null ),
    edit: Object( window.wp.element.createElement )( ContentIframe, null ),
    canMakePayment: () => true,
    ariaLabel: window.wp.htmlEntities.decodeEntities( coinpays_payment_gateway ? coinpays_payment_gateway.title : ''),
};

window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway_Iframe )
