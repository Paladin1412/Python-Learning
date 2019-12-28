
class TreeNode:
    def __init__(self, x):
        self.val = x
        self.left = None
        self.right = None
class Solution:
    def __init__(self):
        self.X = [] #按顺序储存节点的列表
    def qianxu(self,phead):
        root = phead
        # 终止判定
        if root != None:
            # 添加每一步根节点
            self.X.append(root.val)
            # 先向左搜寻
            left = root.left
            self.qianxu(left)
            # 再向右搜寻
            right = root.right
            self.qianxu(right)

a1 = TreeNode(2)
a2 = TreeNode(3)
a3 = TreeNode(5)
a1.left=a2
a1.right=a3
s= Solution()
print(a1.left.val)