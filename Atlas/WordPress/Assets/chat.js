/**
 * Atlas KOS - Frontend Chat Widget
 */
document.addEventListener("DOMContentLoaded", function () {
    // 1. Cargar las configuraciones dinámicas pasadas desde WordPress
    const userName = window.AtlasConfig?.userName || "";
    const titleText = window.AtlasConfig?.titleText || "Asistente Atlas";
    const headerBg = window.AtlasConfig?.headerBg || "#007cba";
    const headerTextColor = window.AtlasConfig?.headerTextColor || "#ffffff";
    const fallbackButtons = window.AtlasConfig?.fallbackButtons || [];

    // Elementos de la interfaz (Selectores unificados)
    const chatWidget = document.getElementById("atlas-chat-widget") || document.querySelector(".atlas-chat-widget");
    const chatHeader = document.querySelector(".atlas-chat-header") || document.querySelector(".chat-header");
    const chatMessages = document.querySelector(".atlas-chat-messages-container") || document.querySelector(".atlas-messages-list");
    const chatInput = document.querySelector(".atlas-chat-input-field") || document.querySelector(".chat-input input"); 
    const sendButton = document.querySelector(".atlas-chat-send-btn") || document.querySelector(".chat-input button");
    const chatToggleBtn = document.getElementById("atlas-chat-toggle") || document.querySelector(".atlas-chat-toggle");

    // Aplicar estilos personalizados a la cabecera dinámicamente
    if (chatHeader) {
        chatHeader.style.backgroundColor = headerBg;
        chatHeader.style.color = headerTextColor;
        let titleElement = chatHeader.querySelector("h3") || chatHeader.querySelector(".atlas-chat-title");
        if (!titleElement) {
            titleElement = document.createElement("h3");
            titleElement.style.margin = "0";
            titleElement.style.fontSize = "16px";
            titleElement.style.fontWeight = "bold";
            chatHeader.appendChild(titleElement);
        }
        titleElement.textContent = titleText;
        titleElement.style.color = headerTextColor;
    }

    // Inyectar animación CSS para los tres puntos suspensivos ("Pensando")
    if (!document.getElementById("atlas-blink-style")) {
        const style = document.createElement("style");
        style.id = "atlas-blink-style";
        style.innerText = `
            @keyframes atlasBlink {
                0% { opacity: .2; }
                20% { opacity: 1; }
                100% { opacity: .2; }
            }
            .atlas-dot {
                animation: atlasBlink 1.4s infinite both;
                font-weight: bold;
                display: inline-block;
            }
            .atlas-dot:nth-child(2) { animation-delay: .2s; }
            .atlas-dot:nth-child(3) { animation-delay: .4s; }
        `;
        document.head.appendChild(style);
    }

    // Función de inserción de mensajes en pantalla (Segura contra cargas rápidas)
    const appendMessage = (sender, text, htmlContent = "") => {
        if (!chatMessages) return null;

        const msgDiv = document.createElement("div");
        msgDiv.className = `atlas-message atlas-message-${sender}`;
        
        const bubbleStyle = sender === 'user' 
            ? `background: ${headerBg}; color: ${headerTextColor}; margin-left: auto; border-radius: 15px 15px 0 15px;` 
            : `background: #f0f0f1; color: #333; margin-right: auto; border-radius: 15px 15px 15px 0;`;

        msgDiv.innerHTML = `
            <div class="atlas-message-bubble" style="padding: 10px 14px; margin-bottom: 12px; max-width: 85%; width: fit-content; box-shadow: 0 1px 2px rgba(0,0,0,0.05); ${bubbleStyle}">
                <div class="atlas-message-text" style="font-size: 13px; line-height: 1.4; word-break: break-word;">${text}</div>
                ${htmlContent ? `<div class="atlas-message-extra" style="margin-top: 8px;">${htmlContent}</div>` : ""}
            </div>
        `;

        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return msgDiv;
    };

    // Auxiliar para obtener el icono o imagen de las acciones comerciales
    const getIconHtml = (iconValue, color = '#ffffff') => {
        if (!iconValue) return "";
        if (iconValue.startsWith("http") || iconValue.includes(".") || iconValue.startsWith("/")) {
            return `<img src="${iconValue}" style="width:14px; height:14px; object-fit:contain; display:inline-block; vertical-align:middle; margin-right:5px;" />`;
        }
        return `<i data-lucide="${iconValue}" style="width:14px; height:14px; color:${color}; display:inline-block; vertical-align:middle; margin-right:5px;"></i>`;
    };

    // --- SALUDO INICIAL DE BIENVENIDA ---
    const sendWelcomeMessage = () => {
        if (!chatMessages) return;
        if (chatMessages.children.length > 0) return;

        let greeting = "¡Hola! ¿En qué te puedo ayudar hoy?";
        if (userName) {
            greeting = `¡Hola, <strong>${userName}</strong>! ¿En qué te puedo ayudar hoy?`;
        }
        appendMessage("bot", greeting);
    };

    sendWelcomeMessage();

    // --- ENVIAR MENSAJE AL CONTROLADOR (ALINEADO CON POST & ASK ENDPOINT) ---
    const handleUserMessage = async () => {
        if (!chatInput) return;
        const queryText = chatInput.value.trim();
        if (!queryText) return;

        // Pintar pregunta en pantalla
        appendMessage("user", queryText);
        chatInput.value = "";

        // Burbuja temporal de pensamiento
        const thinkingBubble = appendMessage("bot", `
            <div class="atlas-thinking" style="display:flex; align-items:center; gap:4px; height: 18px;">
                <span style="font-style:italic; font-size:12px; color:#777;">Un momento, pensando</span>
                <span class="atlas-dot">.</span>
                <span class="atlas-dot">.</span>
                <span class="atlas-dot">.</span>
            </div>
        `);

        // Delay para simular procesamiento IA
        setTimeout(async () => {
            if (thinkingBubble) {
                thinkingBubble.remove();
            }

            try {
                const apiBase = window.AtlasConfig?.restUrl || "/wp-json/";
                
                // Formateamos la llamada REST según el tipo de enlaces en local
                let askUrl;
                if (apiBase.includes('?') || !apiBase.includes('/wp-json/')) {
                    const cleanBase = apiBase.split('?')[0];
                    askUrl = `${cleanBase}?rest_route=/atlas/v1/ask`;
                } else {
                    askUrl = `${apiBase}atlas/v1/ask`;
                }

                // CORRECCIÓN CLAVE: Enviamos una petición POST con payload JSON, como espera AskController.
                const response = await fetch(askUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        question: queryText,
                        url: window.location.href
                    })
                });

                if (!response.ok) {
                    throw new Error("Respuesta de servidor no satisfactoria.");
                }

                const data = await response.json();

                // CORRECCIÓN CLAVE: Evaluamos success y leemos el campo text
                if (data && data.success) {
                    let actionsHtml = "";

                    // Procesar la botonera comercial dinámica de forma adaptativa
                    if (data.actions) {
                        let primaryList = [];
                        let secondaryAction = null;

                        if (Array.isArray(data.actions)) {
                            primaryList = data.actions;
                        } else if (data.actions.primary_list) {
                            primaryList = data.actions.primary_list;
                            secondaryAction = data.actions.secondary;
                        }

                        if (primaryList.length > 0 || secondaryAction) {
                            actionsHtml += `<div style="display:flex; flex-direction:column; gap:8px; margin-top:10px; width:100%;">`;
                            
                            // ◄ MODIFICACIÓN: Renderizamos primero el botón secundario (Leer Artículo)
                            if (secondaryAction) {
                                const secBg = secondaryAction.styles?.backgroundColor || '#f0f0f1';
                                const secColor = secondaryAction.styles?.color || '#3c434a';
                                actionsHtml += `
                                    <a href="${secondaryAction.url}" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding: 10px 12px; background:${secBg}; color:${secColor}; text-decoration:none; border-radius:50px; font-size:12px; font-weight:bold; width:100%; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box;">
                                        <span>${secondaryAction.label}</span>
                                    </a>
                                `;
                            }

                            // ◄ MODIFICACIÓN: Renderizamos después los botones de la lista principal
                            primaryList.forEach(btn => {
                                const btnBg = btn.styles?.backgroundColor || headerBg;
                                const btnColor = btn.styles?.color || '#ffffff';
                                const iconMarkup = getIconHtml(btn.icon, btnColor);

                                actionsHtml += `
                                    <a href="${btn.url}" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding: 10px 12px; background:${btnBg}; color:${btnColor}; text-decoration:none; border-radius:50px; font-size:12px; font-weight:bold; width:100%; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box;">
                                        ${iconMarkup}
                                        <span>${btn.label}</span>
                                    </a>
                                `;
                            });
                            
                            actionsHtml += `</div>`;
                        }
                    }

                    appendMessage("bot", data.text, actionsHtml);

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }

                } else {
                    // Fallback para fallbackButtons en caso de no hallar respuesta
                    let fallbackHtml = "";

                    if (fallbackButtons && fallbackButtons.length > 0) {
                        fallbackHtml += `<div style="display:flex; flex-direction:column; gap:8px; margin-top:10px; width:100%;">`;
                        
                        fallbackButtons.forEach(btn => {
                            const btnBg = btn.styles?.backgroundColor || headerBg;
                            const btnColor = btn.styles?.color || '#ffffff';
                            const iconMarkup = getIconHtml(btn.icon, btnColor);

                            fallbackHtml += `
                                <a href="${btn.url}" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding: 10px 12px; background:${btnBg}; color:${btnColor}; text-decoration:none; border-radius:50px; font-size:12px; font-weight:bold; width:100%; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box;">
                                    ${iconMarkup}
                                    <span>${btn.label}</span>
                                </a>
                            `;
                        });
                        
                        fallbackHtml += `</div>`;
                    }

                    const defaultFallbackText = (data && data.text) || "Lo siento, no he encontrado información exacta sobre eso. ¿Te gustaría ponerte en contacto con nosotros?";
                    appendMessage("bot", defaultFallbackText, fallbackHtml);

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            } catch (error) {
                console.error("Error en Atlas Chat:", error);
                appendMessage("bot", "Vaya, parece que tengo problemas de conexión en este momento.");
            }
        }, 1200);
    };

    // Asociación de eventos
    if (sendButton) {
        sendButton.addEventListener("click", handleUserMessage);
    }

    if (chatInput) {
        chatInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                handleUserMessage();
            }
        });
    }

    // Evento de apertura/cierre
    if (chatToggleBtn && chatWidget) {
        chatToggleBtn.addEventListener("click", function () {
            chatWidget.classList.toggle("active");
            chatWidget.classList.toggle("open");
            
            if (chatWidget.style.display === "none" || !chatWidget.style.display) {
                chatWidget.style.display = "flex";
                if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                chatWidget.style.display = "none";
            }
        });
    }
});