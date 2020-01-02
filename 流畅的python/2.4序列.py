"""Right Example"""
board = [['_']*3 for i in range(3)]
print(board)
board[1][2]='X'
print(board)

"""Wrong Example"""
wrong_board = [['_']*3]*3
"""生成的列表是指向同一个列表的引用"""
print(wrong_board)
wrong_board[1][2]='X'
print(wrong_board)

"""Wrong Example 2"""
row=['_']*3
board=[]
[board.append(row) for i in range(3)]
print(board)
board[1][2]='X'
print(board)

"""对不可变序列进行操作效率低"""
l=[]
[l.append(i) for i in range(1,4)]
print(id(l))
l*=2
print(id(l))
t=(1,2,3)
t *=2
print(id(t))



