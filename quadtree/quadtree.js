// 整个逻辑的坐标系的原点在左上角，与canvas保持一致


/**
 * 检查小物体是否在大物体中
 * @param {Item} bigone 
 * @param {Item} smallone 
 */
function inBoundary(bigone, smallone) {
    return smallone.topLeftP.x >= bigone.topLeftP.x
        && smallone.topLeftP.y >= bigone.topLeftP.y
        && smallone.botRightP.x <= bigone.botRightP.x
        && smallone.botRightP.y <= bigone.botRightP.y 
}

function pointInBoundary(item, point) {
    
    return point.x >= item.topLeftP.x
        && point.y >= item.topLeftP.y
        && point.x <= item.botRightP.x
        && point.y <= item.botRightP.y  
}

function intersect(itema, itemb) {
    // max(Xa1,Xb1) <= min(Xa2,Xb2)
    // max(Ya1,Yb1) <= min(Ya2,Yb2)
    var xa1 = itema.topLeftP.x
    var ya1 = itema.topLeftP.y
    var xa2 = itema.botRightP.x
    var ya2 = itema.botRightP.y

    var xb1 = itemb.topLeftP.x
    var yb1 = itemb.topLeftP.y
    var xb2 = itemb.botRightP.x
    var yb2 = itemb.botRightP.y
    
    return Math.max(xa1,xb1) <= Math.min(xa2, xb2) && Math.max(ya1,yb1) <= Math.min(ya2, yb2)

}

/**
 * 求两个矩形相交的部分
 * @param {Item} item1 
 * @param {Item} item2 
 */
function intersectRect(item1, item2) {
    var topLeftP = new Point(0,0);
    var botRightP = new Point(0,0);;
    topLeftP.x = Math.max(item1.topLeftP.x, item2.topLeftP.x)
    topLeftP.y = Math.max(item1.topLeftP.y, item2.topLeftP.y)
    botRightP.x = Math.min(item1.botRightP.x, item2.botRightP.x)
    botRightP.y = Math.min(item1.botRightP.y, item2.botRightP.y)

    return new Item(topLeftP, botRightP)
}

var Point = function (x, y) {
    this.x = x;
    this.y = y;
}

// 代表出现的矩形物体
// topLeftP 矩形物体左上角的点
var Item = function (topLeftP, botRightP) {
    this.topLeftP = topLeftP;
    this.botRightP = botRightP;
}

// 四叉树是一个递归结构。每个非叶子节点都有4个指向其他Node的指针
var Node = function(topLeftP, botRightP) {
    this.tlNode = null;         // top left
    this.trNode = null;         // top right
    this.blNode = null;         // bottom left
    this.brNode = null;         // bottom right

    this.data = new Array();            // data 域，用来存储属于这个Node范围

    this.topLeftP = topLeftP;       // 左下角点
    this.botRightP = botRightP;     // 右上角点
}

Node.prototype.inBoundary = function (item) {
    return inBoundary(this, item)
}

Node.prototype.intersect = function (item) {
    return intersect(this, item)
}

Node.prototype.pushItem = function (item) {
    if (this.tlNode == null) {
        // 没有子树
        // 检查curNode中存储的物体数量.超过6个开始分裂
        if(this.data.length >= 6) {
            var leftX = this.topLeftP.x;
            var rightX = this.botRightP.x;
            var midX = (leftX + rightX) / 2;
            var topY = this.topLeftP.y;
            var botY = this.botRightP.y;
            var midY = (topY + botY) / 2;
            
            this.tlNode = new Node(new Point(leftX, topY ),new Point(midX, midY));
            this.trNode = new Node(new Point(midX, topY), new Point(rightX, midY));
            this.blNode = new Node(new Point(leftX, midY), new Point(midX, botY));
            this.brNode = new Node(new Point(midX, midY), new Point(rightX, botY));

            // 将当前节点的数据重新分配
            while((tmp = this.data.pop()) != null) {
                this.pushItem(tmp)
            }

            // 把要放的item放在合适的位置
            this.pushItem(item)
        } else {
            this.data.push(item);
        }
        return;
    } else {
        // 判断item的位置
        if (this.tlNode.inBoundary(item) || this.tlNode.intersect(item)) {
            this.tlNode.data.push(item);
        }
        
        if (this.trNode.inBoundary(item) || this.trNode.intersect(item)) {
            this.trNode.data.push(item);
        } 
        
        if (this.blNode.inBoundary(item) || this.blNode.intersect(item)) {
            this.blNode.data.push(item);
        }
        
        if (this.brNode.inBoundary(item) || this.brNode.intersect(item)) {
            this.brNode.data.push(item);
        }
    }
}

// 这里四叉树的实现特点包括:
// 1.只有叶子节点存储物体
// 2.和节点相交的物体会被放在这个节点中，因此一个与边界相交的节点会被冗余存储

var QuadTree = function(topLeftP, botRightP) {
    this.root = new Node(topLeftP, botRightP);     // 根节点
}


QuadTree.prototype.insert = function (item) {

    var curNode = this.root;

    // 找到合适的curNode
    while (curNode.tlNode != null) { // 有子节点
        if (curNode.tlNode.inBoundary(item)) {
            curNode = curNode.tlNode
        } else if (curNode.trNode.inBoundary(item)) {
            curNode = curNode.trNode
        } else if (curNode.blNode.inBoundary(item)) {
            curNode = curNode.blNode
        } else if (curNode.brNode.inBoundary(item)) {
            curNode = curNode.brNode
        } else {
            // 如果子节点都不完全包含，则当前点就是要找的合适的点
            break;
        }
    }

    curNode.pushItem(item);
}

QuadTree.prototype.query = function(leftTop, rightBot) {
    return this.queryNode(this.root, leftTop, rightBot)
}

/**
 * 给定一个矩形范围，查询在这个范围内的物体
 */
QuadTree.prototype.queryNode = function(node, leftTop, rightBot) {
    var curNode = node;
    var item = new Item(leftTop, rightBot);

    // 找到合适的curNode
    while (curNode.tlNode != null) { // 有子节点
        if (curNode.tlNode.inBoundary(item)) {
            curNode = curNode.tlNode
        } else if (curNode.trNode.inBoundary(item)) {
            curNode = curNode.trNode
        } else if (curNode.blNode.inBoundary(item)) {
            curNode = curNode.blNode
        } else if (curNode.brNode.inBoundary(item)) {
            curNode = curNode.brNode
        } else {
            // 如果子节点都不完全包含，则当前点就是要找的合适的点
            break;
        }
    }

    var result = new Array();

    // 检查这个点有没有子节点
    if (curNode.tlNode == null) {
        result = result.concat(curNode.data);
    } else {
        // 有子节点并且相交了。在相交处将该查询分割为更小的方块查询
        if (curNode.tlNode.intersect(item)) {
            // 分割
            var newQuery = intersectRect(curNode.tlNode, item);
            console.log("分割的图形", newQuery)
            result = result.concat(this.queryNode(curNode.tlNode, newQuery.topLeftP, newQuery.botRightP) )
        }

        if (curNode.trNode.intersect(item)) {
            // 分割
            var newQuery = intersectRect(curNode.trNode, item);
            console.log("分割的图形", newQuery)
            result = result.concat(this.queryNode(curNode.trNode, newQuery.topLeftP, newQuery.botRightP))
        }

        if (curNode.blNode.intersect(item)) {
            // 分割
            var newQuery = intersectRect(curNode.blNode, item);
            console.log("分割的图形", newQuery)
            result = result.concat(this.queryNode(curNode.blNode, newQuery.topLeftP, newQuery.botRightP) )
        }

        if (curNode.brNode.intersect(item)) {
            // 分割
            var newQuery = intersectRect(curNode.brNode, item);
            console.log("分割的图形", newQuery)
            result = result.concat(this.queryNode(curNode.brNode, newQuery.topLeftP, newQuery.botRightP) )
        }

    }

    return result;
}

QuadTree.prototype.search = function() {

}
