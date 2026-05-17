import { Node, mergeAttributes } from "@tiptap/core"

const VideoNode = Node.create({
    name: "video",

    group: "block",

    atom: true,

    selectable: true,

    draggable: true,

    addAttributes() {
        return {
            src: {
                default: null,
            },
            controls: {
                default: true,
                parseHTML: (element) => element.hasAttribute("controls"),
                renderHTML: (attributes) => (attributes.controls ? { controls: "true" } : {}),
            },
            preload: {
                default: "metadata",
            },
        }
    },

    parseHTML() {
        return [
            {
                tag: "video[src]",
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return ["video", mergeAttributes(HTMLAttributes)]
    },
})

export default VideoNode