/**
 * Atlas KOS - Frontend Chat Widget
 */
document.addEventListener("DOMContentLoaded", function () {
    // 1. Extraer configuraciones de WordPress locales
    const userName = window.AtlasConfig?.userName || "";
    const titleText = window.AtlasConfig?.titleText || "Asistente Atlas";
    const headerBg = window.AtlasConfig?.headerBg || "#007cba";
    const headerTextColor = window.AtlasConfig?.headerTextColor || "#ffffff";
    const fallbackButtons = window.AtlasConfig?.fallbackButtons || [];

    // 2. Selectores de la Interfaz
    const chatWidget = document.getElementById("atlas-chat-widget") || document.querySelector(".atlas-chat-widget");
    const chatHeader = document.querySelector(".atlas-chat-header") || document.querySelector(".chat-header");
    const chatMessages = document.querySelector(".atlas-chat-messages-container") || document.querySelector(".atlas-messages-list") || document.querySelector(".chat-messages");
    const chatInput = document.querySelector(".atlas-chat-input-field") || document.querySelector(".atlas-chat-input") || document.querySelector(".chat-input input");
    const sendButton = document.querySelector(".atlas-chat-send-btn") || document.querySelector(".atlas-send-button") || document.querySelector(".chat-input button");
    const chatToggleBtn = document.getElementById("atlas-chat-toggle") || document.querySelector(".atlas-chat-toggle");

    // Inyectar animación CSS para los puntos suspensivos ("Pensando")
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

    // 3. Aplicar colores y textos dinámicos en la cabecera
    if (chatHeader) {
        chatHeader.style.backgroundColor = headerBg;
        chatHeader.style.color = headerTextColor;

        let titleElement = chatHeader.querySelector("h3") || chatHeader.querySelector(".atlas-chat-title") || chatHeader.querySelector(".chat-title");
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

    // 4. Función de inserción de mensajes en pantalla
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

    // 5. Auxiliar para obtener el icono o imagen de las acciones comerciales
    const getIconHtml = (iconValue, color = '#ffffff') => {
        if (!iconValue) return "";
        if (iconValue.startsWith("http") || iconValue.includes(".") || iconValue.startsWith("/")) {
            return `<img src="${iconValue}" style="width:14px; height:14px; object-fit:contain; display:inline-block; vertical-align:middle; margin-right:5px;" />`;
        }
        return `<i data-lucide="${iconValue}" style="width:14px; height:14px; color:${color}; display:inline-block; vertical-align:middle; margin-right:5px;"></i>`;
    };

    // 6. Mensaje de bienvenida inmediato y dinámico
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

    // 7. Lógica de procesamiento de las respuestas
    const handleUserMessage = async () => {
        if (!chatInput) return;
        const queryText = chatInput.value.trim();
        if (!queryText) return;

        // Mostrar de inmediato la pregunta del usuario
        appendMessage("user", queryText);
        chatInput.value = "";

        // Inyectar la animación "Pensando" y guardar su referencia de manera segura
        const thinkingBubble = appendMessage("bot", `
            <div class="atlas-thinking" style="display:flex; align-items:center; gap:4px; height: 18px;">
                <span style="font-style:italic; font-size:12px; color:#777;">Un momento, pensando</span>
                <span class="atlas-dot">.</span>
                <span class="atlas-dot">.</span>
                <span class="atlas-dot">.</span>
            </div>
        `);

        // Simulación de demora cognitiva (1.2 segundos) para realismo de la IA
        setTimeout(async () => {
            // Eliminar la animación antes de pintar la respuesta
            if (thinkingBubble) {
                thinkingBubble.remove();
            }

            try {
                // Obtener URL de la API de WordPress enviada por WordPressAdapter
                const apiBase = window.AtlasConfig?.restUrl || "/wp-json/";
                let chatUrl, askUrl;

                // Soporte inteligente para URLs que contienen puertos (como localhost:10095) o enlaces simples
                if (apiBase.includes('?') || !apiBase.includes('/wp-json/')) {
                    const cleanBase = apiBase.split('?')[0];
                    chatUrl = `${cleanBase}?rest_route=/atlas/v1/chat&query=${encodeURIComponent(queryText)}`;
                    askUrl = `${cleanBase}?rest_route=/atlas/v1/ask&query=${encodeURIComponent(queryText)}`;
                } else {
                    chatUrl = `${apiBase}atlas/v1/chat?query=${encodeURIComponent(queryText)}`;
                    askUrl = `${apiBase}atlas/v1/ask?query=${encodeURIComponent(queryText)}`;
                }

                // Intentamos realizar la consulta al endpoint principal /chat
                let response = await fetch(chatUrl);
                let data = null;

                // Si devuelve un 404, probamos inteligentemente con el endpoint alternativo /ask
                if (response.status === 404) {
                    response = await fetch(askUrl);
                }

                if (!response.ok) {
                    throw new Error("Error en la respuesta de la red.");
                }

                data = await response.ok ? await response.json() : null;

                if (data && data.found) {
                    appendMessage("bot", data.answer);
                } else {
                    // Si la respuesta no es encontrada, mostramos el fallback configurado
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

                    const defaultFallbackText = (data && data.answer) || "Lo siento, no he encontrado información exacta sobre eso. ¿Te gustaría ponerte en contacto con nosotros?";
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

    // 8. Asociación de eventos para el Input y Botón de envío
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

    // 9. Evento para abrir/cerrar la ventana del chat
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